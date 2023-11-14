<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Nodes\Actions;

use App\Domain\Helpers\Actions\BuildKerberosCRDNameAction;
use App\Domain\Helpers\Actions\BuildKerberosPrincipalAction;
use App\Domain\Nodes\CRDs\KerberosKey;
use App\Domain\Nodes\Models\Node;
use App\Domain\Support\Actions\MakeConsumptionFrameworkRequest;
use App\Exceptions\ActionFailException;
use App\Exceptions\ActionForbiddenException;
use Illuminate\Support\Facades\Log;
use RenokiCo\LaravelK8s\KubernetesCluster;
use function func_get_args;

class DeleteNodeAction
{

    /**
     * This action deletes an empty node and cleans up all connections for the node. It also removes all principals
     * and config store entries, effectively undoing the creation of the node.
     **/

    private function authorise(Node $node)
    {
        if (!auth()->user()->administrator && !auth()->user()->accessibleNodes->contains($node)) {
            throw new ActionForbiddenException('You do not have permission to delete this node.', 401);
        }
    }

    private function validate(Node $node)
    {
        // Check that the node has no devices
        if ($node->devices->count() > 0) {
            throw new ActionFailException('You cannot delete a node that has devices. Delete all devices from the node first and try again.');
        }
    }

    public function execute(Node $node)
    {

        // Validate and authorise the request
        $this->authorise(...func_get_args());
        $this->validate(...func_get_args());

        // Add an entry in the Config Store to allow the EDO to provision the node
        (new MakeConsumptionFrameworkRequest)->execute(
            type: 'delete',
            service: 'configdb',
            url: config('manager.configdb_service_url') . '/v1/app/f2b9417a-ef7f-421f-b387-bb8183a48cdb/object/' . $node->uuid,
        );

        $node->delete();

        Log::info('Node deleted', ['node' => $node]);

        return action_success();


    }

}
