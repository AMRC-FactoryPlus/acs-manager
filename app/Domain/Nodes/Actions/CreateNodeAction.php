<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Nodes\Actions;

use App\Domain\Groups\Models\Group;
use App\Domain\Helpers\Actions\BuildKerberosCRDNameAction;
use App\Domain\Helpers\Actions\BuildKerberosPrincipalAction;
use App\Domain\Nodes\CRDs\KerberosKey;
use App\Domain\Nodes\Models\Node;
use App\Domain\Support\Actions\MakeConsumptionFrameworkRequest;
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
        $nodeName,
        $destinationCluster,
        $destinationNode
    ) {
        // ! TODO: Ensure that we validate that the cluster and node actually exists by hitting the Config Store

        // =========================
        // Validate User Permissions
        // =========================

        if (!auth()->user()->administrator) {
            throw new ActionForbiddenException('Only administrators can create new nodes.');
        }

        if ($group->nodes()->where('node_id', $nodeName)->exists()) {
            throw new ActionFailException('This group already has a node with this name.');
        }

        // ===================
        // Perform the Action
        // ===================

        // Create the object in the ConfigDB
        $uuid = json_decode(
            (new MakeConsumptionFrameworkRequest)->execute(
                type: 'post', service: 'configdb', url: config(
                    'manager.configdb_service_url'
                ) . '/v1/object', payload: [
                "class" => "00da3c0b-f62b-4761-a689-39ad0c33f864",
            ]
            )['data']->body()
        )->uuid;

        $node = Node::create([
            'node_id' => $nodeName,
            'k8s_hostname' => $destinationCluster . '/' . $destinationNode,
            'principal' => $nodeName . '/' . $destinationCluster . '/' . $destinationNode,
            'group_id' => $group->id,
            'is_admin' => 0,
            'is_valid' => true,
            'uuid' => $uuid,
        ]);

        // Create a General Object Information entry
        (new MakeConsumptionFrameworkRequest)->execute(
            type: 'put',
            service: 'configdb',
            url: config('manager.configdb_service_url') . '/v1/app/64a8bfa9-7772-45c4-9d1a-9e6290690957/object/' . $uuid,
            payload: [
                "name" => $nodeName . '/' . $destinationCluster . '/' . $destinationNode,
            ]
        );

        // Add an entry in the Config Store to allow the EDO to provision the node
        (new MakeConsumptionFrameworkRequest)->execute(
            type: 'put',
            service: 'configdb',
            url: config('manager.configdb_service_url') . '/v1/app/f2b9417a-ef7f-421f-b387-bb8183a48cdb/object/' . $uuid,
            payload: [
                "name" => $nodeName,
                "charts" => ["edge-agent"],
                "cluster" => $destinationCluster,
                "hostname" => $destinationNode,
            ]
        );


        return action_success([
            'node' => [
                'node_id' => $node->node_id,
                'uuid' => $node->uuid,
                'id' => $node->id,
            ],
        ]);
    }
}
