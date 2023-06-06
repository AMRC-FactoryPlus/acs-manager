<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Support\ServiceClient;

class ConfigDB extends ServiceInterface
{
    static $serviceName = "configdb";

    public function createObject (string $class, string $uuid = null, string $name = null)
    {
        $payload = ["class" => $class];
        if (!is_null($uuid)) {
            $payload["uuid"] = $uuid;
        }

        $this->fetch(
            type: 'post',
            url: '/v1/object',
            payload: $payload,
        );

        if (!is_null($name)) {
            $this->putConfig(UUIDs\App::Info, $uuid, ["name" => $name]);
        }
    }

    public function putConfig (string $app, string $obj, $payload)
    {
        $this->fetch(
            type: 'put',
            url: sprintf("/v1/app/%s/object/%s", $app, $obj),
            payload: $payload,
        );
    }
}
