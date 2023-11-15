<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Nodes\Actions;

use AMRCFactoryPlus\Utilities\ServiceClient;
use AMRCFactoryPlus\Utilities\ServiceClient\ServiceClientException;
use AMRCFactoryPlus\Utilities\ServiceClient\UUIDs\App;
use App\Domain\Groups\Models\Group;
use App\Domain\Nodes\Models\Node;
use App\Exceptions\ActionFailException;
use App\Exceptions\ActionForbiddenException;

class CreateNodeAction
{
    /**
     * This action creates a node in the given group
     *
     * @throws ActionForbiddenException
     * @throws ActionFailException
     * @throws ServiceClientException
     */
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

        $fplus = resolve(ServiceClient::class);
        $configDB = $fplus->getConfigDB();

        // Create the object in the ConfigDB
        $uuid = $configDB->createObject(ServiceClient\UUIDs\Klass::CellGateway)['uuid'];

        $node = Node::create([
            'node_id' => $nodeName,
            'k8s_hostname' => $destinationCluster . '/' . $destinationNode,
            'principal' => $nodeName . '/' . $destinationCluster . '/' . $destinationNode,
            'group_id' => $group->id,
            'is_admin' => 0,
            'is_valid' => true,
            'uuid' => $uuid,
        ]);

        $configDB->putConfig(App::Info, $uuid, [
            "name" => $nodeName . '/' . $destinationCluster . '/' . $destinationNode,
        ]);

        // Create an entry in the Edge Agent Deployment app to trigger the deployment of the edge agent
        $configDB->putConfig(App::EdgeAgentDeployment, $uuid, [
            "name" => $nodeName,
            "charts" => ["edge-agent"],
            "cluster" => $destinationCluster,
            "hostname" => $destinationNode,
        ]);

        return action_success([
            'node' => [
                'node_id' => $node->node_id,
                'uuid' => $node->uuid,
                'id' => $node->id,
            ],
        ]);
    }
}
