<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Support\ServiceClient;

use App\Domain\Support\ServiceClient;

abstract class ServiceInterface
{
    static $serviceName;

    public function __construct (public ServiceClient $client)
    {
    }

    public function fetch(string $type, string $url, $payload = null, $file = null)
    {
        return $this->client->http()
            ->fetch($type, static::$serviceName, $url, $payload, $file);
    }
}
