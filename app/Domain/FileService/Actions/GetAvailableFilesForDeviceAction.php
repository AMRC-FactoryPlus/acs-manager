<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\FileService\Actions;

use App\Domain\Devices\Models\Device;
use App\Domain\Support\ServiceClient;

use function func_get_args;

class GetAvailableFilesForDeviceAction
{
    public function execute(Device $device)
    {
        // Validate and authorise the request
        $this->authorise(...func_get_args());
        $this->validate(...func_get_args());

        $response = ServiceClient::get()->http()->fetch(
            type: 'get',
            service: 'file-service',
            url: '/config/' . $device->schema_uuid
        );

        return action_success($response->json());
    }

    /**
     * This action gets all available files to upload as per the file service
     **/
    private function authorise()
    {
    }

    private function validate()
    {
    }
}
