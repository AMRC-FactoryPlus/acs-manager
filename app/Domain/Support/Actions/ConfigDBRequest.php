<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Support\Actions;

use App\Domain\Support\UUIDs\CDApp;

class ConfigDBRequest
{
    public function createObject (string $class, string $uuid = null, string $name = null)
    {
        $payload = ["class" => $class];
        if (!is_null($uuid)) {
            $payload["uuid"] = $uuid;
        }

        (new MakeConsumptionFrameworkRequest)->execute(
            type: 'post',
            service: 'configdb',
            url: config('manager.configdb_service_url') . '/v1/object',
            payload: $payload,
        );

        if (!is_null($name)) {
            (new ConfigDBRequest)->putConfig(CDApp::Info, $uuid,
                ["name" => $name]);
        }
    }

    public function putConfig (string $app, string $obj, $payload)
    {
        (new MakeConsumptionFrameworkRequest)->execute(
            type: 'put',
            service: 'configdb',
            url: sprintf("%s/v1/app/%s/object/%s",
                config('manager.configdb_service_url'), $app, $obj),
            payload: $payload,
        );
    }
}
