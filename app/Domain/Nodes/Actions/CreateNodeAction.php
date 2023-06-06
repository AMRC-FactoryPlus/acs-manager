<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Nodes\Actions;

use App\Domain\Groups\Models\Group;
use App\Domain\Nodes\CRDs\KerberosKey;
use App\Domain\Nodes\Models\Node;
use App\Domain\Support\ServiceClient;
use App\Domain\Support\ServiceClient\UUIDs;
use App\Exceptions\ActionErrorException;
use App\Exceptions\ActionFailException;
use App\Exceptions\ActionForbiddenException;
use Illuminate\Support\Facades\Log;
use RenokiCo\LaravelK8s\KubernetesCluster;
use RenokiCo\PhpK8s\Exceptions\KubernetesAPIException;

class CreateNodeAction
{
    /**
     * This action creates a node in the given group
     **/
    /*
     * Constraints:
     * - The user must be an admin
     */

    public function execute(
        Group $group,
        $nodeId,
        $isGateway = true,
        $enabled = true,
        $expiry = null,
        $nodeHostname = null
    ) {
        // =========================
        // Validate User Permissions
        // =========================

        if (! auth()->user()->administrator) {
            throw new ActionForbiddenException('Only administrators can create new nodes.');
        }

        if ($isGateway && ! $nodeHostname) {
            if (! $group->cluster->has_central_agents) {
                throw new ActionFailException('Soft Gateways can only be created in clusters with central agent nodes');
            }

            if (config('manager.multi_cluster')) {
                throw new ActionFailException('Deploying Soft Gateways on multi-cluster instances is not enabled.');
            }
        }

        if ($group->nodes()
            ->where('node_id', $nodeId)
            ->exists()) {
            throw new ActionFailException('This group already has a node with this name.');
        }

        // ===================
        // Perform the Action
        // ===================

        $isCellGateway = ! is_null($nodeHostname);
        if (! $isCellGateway) {
            $nodeHostname = 'soft';
        }

        # This preserves the existing behaviour for k8s_hostname of
        #   cell gateway: hostname to deploy to
        #   soft gateway: "soft"
        #   not a gateway: null
        # I don't know if this is important elsewhere. This means that
        # we cannot tell if a Node is a Cell or Soft gateway from the node
        # record.
        $node = Node::create([
            'node_id' => $nodeId,
            'k8s_hostname' => $isGateway ? $nodeHostname : null,
            'principal' => $this->getKerberosPrincipal($group, $nodeHostname, $nodeId),
            'expiry_date' => $expiry,
            'group_id' => $group->id,
            'is_admin' => 0,
            'is_valid' => $enabled,
        ])->fresh();

        if ((bool) $isGateway === true) {
            if (! in_array(config('app.env'), ['local', 'testing'])) {
                $this->createK8sObjects($node, $isCellGateway);
                $this->createServiceEntries($node, $isCellGateway);
            }
        }

        return action_success([
            'node' => [
                'node_id' => $node->node_id,
                'uuid' => $node->uuid,
                'id' => $node->id,
            ],
        ]);
    }

    public function createK8sObjects($node, $isCellGateway)
    {
        $group = $node->group;
        $nodeId = $node->node_id;
        $nodeUuid = $node->uuid;
        $principal = $node->principal;
        $nodeHostname = $node->k8s_hostname;

        $namespace = $group->cluster->namespace;
        $cluster = KubernetesCluster::inClusterConfiguration();

        $k8sSafeName = substr(
            str_replace('_', '-', $this->getKerberosKeyCRDName($group, $nodeHostname, $nodeId)),
            0,
            60
        );

        Log::debug('Creating kerberos key for edge agent', [
            'type' => 'Password',
            'principal' => $principal,
            'secret' => 'edge-agent-secrets-' . $nodeUuid . '/keytab',
        ]);

        $spec = [
            'name' => $k8sSafeName,
            'type' => 'Password',
            'principal' => $principal,
            'secret' => 'edge-agent-secrets-' . $nodeUuid . '/keytab',
        ];

        // If we're sending this to another cluster then we need to seal the KerberosKey with the kubeseal cert of the remote cluster
        // This assumes that there is a ConfigMap called kubeseal-cert/cert containing the certificate in the same namespace
        if (config('manager.multi_cluster')) {
            $spec['sealWith'] = 'kubeseal-cert/cert';
        }

        // Create a password for the edge agent to use to connect to the MQTT server and store it in the secret
        (new KerberosKey($cluster, [
            'spec' => $spec,
        ]))->setName($k8sSafeName)
            ->setNamespace($namespace)
            ->setLabels([
                'app.kubernetes.io/managed-by' => 'management-app',
                'type' => 'edge-agent-secrets',
                'nodeName' => $nodeId,
                'groupName' => $group->name,
                'nodeUuid' => $nodeUuid,
                'nodeHostname' => $nodeHostname,
            ])
            ->create();

        // Deploy the edge agent
        if ($isCellGateway) {
            $template = config(
                'manager.multi_cluster'
            ) ? 'remote-edge-agent-template.yaml' : 'edge-agent-template.yaml';
        } else {
            $template = config(
                'manager.multi_cluster'
            ) ? 'remote-soft-edge-agent-template.yaml' : 'soft-edge-agent-template.yaml';
        }

        $resources = $cluster->fromTemplatedYamlFile(app_path('/Domain/Nodes/YAML/' . $template), [
            'name' => $k8sSafeName,
            'hostname' => $nodeHostname,
            'nodeUuid' => $nodeUuid,
            'namespace' => $namespace,
            'appUrl' => config('manager.management_app_from_edge'),
            'registry' => config('manager.new_edge_agents.registry'),
            'repository' => config('manager.new_edge_agents.repository'),
            'version' => config('manager.new_edge_agents.version'),
            'debug' => config('manager.new_edge_agents.debug'),
            'pollInterval' => config('manager.new_edge_agents.pollInterval'),
        ]);

        try {
            $resources->createOrUpdate();
        } catch (KubernetesAPIException $e) {
            throw new ActionErrorException($e->getPayload()['message']);
        }
    }

    public function createServiceEntries ($node, $isCellGateway)
    {
        $fplus = ServiceClient::get();
        $cdb = $fplus->configdb();
        $http = $fplus->http();

        $nodeUuid = $node->uuid;
        $groupId = $node->group->name;
        $nodeId = $node->node_id;

        $hostUuid = $isCellGateway
            ? $this->findHostUuid($node)
            : null;

        // Create an entry in the ConfigDB to describe this node using the Cell gateway (00da3c0b-f62b-4761-a689-39ad0c33f864)
        // or Soft gateway (5bee4d24-32e1-44f8-b953-1f86ff4b3e87) class
        // Create entry in the ConfigDB for the General object information Application (64a8bfa9-7772-45c4-9d1a-9e6290690957)
        $cdb->createObject(
            class: $isCellGateway ? UUIDs\Klass::CellGateway : UUIDs\Klass::SoftGateway,
            uuid: $nodeUuid,
            # XXX This is supposed to be a human-readable name. We should request one from the user.
            name: $groupId . "-" . $nodeId,
        );

        // Create entry in the ConfigDB for the Sparkplug address information (8e32801b-f35a-4cbf-a5c3-2af64d3debd7) Application
        $cdb->putConfig(
            app: UUIDs\App::SparkplugAddress, 
            obj: $nodeUuid,
            payload: [
                'node_id' => $nodeId,
                'group_id' => $groupId
            ]
        );

        // Create entry to deploy the Edge Agent. This is practically
        // empty at the moment (all the important information is already
        // elsewhere) but it may expand in the future to include things
        // like additional deployed services.
        $cdb->putConfig(
            app: UUIDs\App::EdgeAgentDeployment,
            obj: $nodeUuid,
            payload: ["host" => $hostUuid],
        );

        // Create entry in the Auth service to map the Kerberos principal to the node UUID
        $http->fetch(
            type: 'post',
            service: 'auth',
            url: '/authz/principal',
            payload: [
                'uuid' => $nodeUuid,
                'kerberos' => $node->principal,
            ]
        );

        // Create an ACL entry to allow the new node to participate as an Edge Node (87e4a5b7-9a89-4796-a216-39666a47b9d2)
        $http->fetch(
            type: 'post',
            service: 'auth',
            url: '/authz/ace',
            payload: [
                'action' => 'add',
                'principal' => $nodeUuid,
                'permission' => '87e4a5b7-9a89-4796-a216-39666a47b9d2',
                'target' => $nodeUuid,
            ]
        );
    }

    function findHostUuid ($node)
    {
        $hostname = $node->k8s_hostname;

        $hosts = ServiceClient::get()->configdb()
            ->searchConfig(UUIDs\App::EdgeHost, ["hostname" => $hostname]);

        if (count($hosts) == 0) {
            throw new ActionErrorException(sprintf(
                "Host %s not found for node %s/%s", 
                    $hostname, $node->group->name, $node->node_id));
        }
        if (count($hosts) != 1) {
            throw new ActionErrorException(sprintf(
                "Found multiple entries for host %s", $hostname));
        }

        return $hosts[0];
    }

    public function getKerberosKeyCRDName($group, $hostname, $nodeId)
    {
        // Convert the GroupName to lowercase

        $prefix = 'sg' . $group->cluster->id;
        // If we have a hostname then this is a Cell Gateway
        if ($hostname) {
            $prefix = 'nd' . $group->cluster->id;
        }

        return $prefix . '-' . strtolower($group->name) . '-' . strtolower($nodeId);
    }

    public function getKerberosPrincipal($group, $hostname, $nodeId)
    {
        return $this->getKerberosKeyCRDName($group, $hostname, $nodeId) . '@' . config('manager.domain');
    }
}
