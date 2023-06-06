<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Support\ServiceClient;

use Illuminate\Support\Facades\Log;

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

    public function getConfig (string $app, string $obj)
    {
        $res = $this->fetch(
            type: "get",
            url: sprintf("/v1/app/%s/object/%s", $app, $obj),
        );

        if (!$rv->ok()) {
            Log::debug(sprintf("ConfigDB fetch for %s/%s failed: %u",
                $app, $obj, $res->status()));
            return;
        }
        return $res->json();
    }

    public function putConfig (string $app, string $obj, $payload)
    {
        $res = $this->fetch(
            type: 'put',
            url: sprintf("/v1/app/%s/object/%s", $app, $obj),
            payload: $payload,
        );

        if (!$res->successful()) {
            throw new ServiceClientException(
                sprintf("Failed to put ConfigDB entry for %s/%s: %u",
                    $app, $obj, $res->status()));
        }
    }

    # This doesn't support querying for results yet.
    public function searchConfig (string $app, array $query)
    {
        $res = $this->fetch(
            type: "get",
            url: sprintf("/v1/app/%s/search", $app),
            query: $query,
        );
        if (!$res->ok()) {
            Log::debug(sprintf("ConfigDB search for %s failed: %u",
                $app, $res->status()));
            return;
        }
        return $res->json();
    }
}
