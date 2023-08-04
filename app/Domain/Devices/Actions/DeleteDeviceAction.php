<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Devices\Actions;

use App\Domain\Devices\Models\Device;
use App\Domain\Nodes\Actions\UpdateEdgeAgentConfigurationForNodeAction;
use App\Exceptions\ActionFailException;
use App\Exceptions\ActionForbiddenException;
use function func_get_args;

class DeleteDeviceAction
{

    /**
     * This action deletes a device from the database and re-generates the edge agent configuration for the node
     **/

    private function authorise(Device $device) {
        if (! auth()->user()->administrator && ! auth()->user()->accessibleNodes->contains($device->node)) {
            throw new ActionForbiddenException('You do not have permission to delete devices for this node.', 401);
        }
    }

    private function validate() {}

    public function execute(Device $device)
    {

        // Validate and authorise the request
        $this->authorise(...func_get_args());
        $this->validate(...func_get_args());

        $device->originMaps()->delete();
        $device->delete();
        (new UpdateEdgeAgentConfigurationForNodeAction())->execute($device->node);

        return action_success();
    }

}
