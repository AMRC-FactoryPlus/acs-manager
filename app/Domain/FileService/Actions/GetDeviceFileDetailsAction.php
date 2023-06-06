<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\FileService\Actions;

use App\Domain\Support\ServiceClient;

use function func_get_args;

class GetDeviceFileDetailsAction
{
    public function execute(string $fileUuid)
    {
        // Validate and authorise the request
        $this->authorise(...func_get_args());
        $this->validate(...func_get_args());

        $response = ServiceClient::get()->http()->fetch(
            type: 'get',
            service: 'file-service',
            url: '/file/' . $fileUuid
        );

        return action_success($response->json());
    }

    /**
     * This action gets details for a given file UUID
     **/
    private function authorise()
    {
    }

    private function validate()
    {
    }
}
