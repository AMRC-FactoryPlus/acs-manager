<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Support;

use App\Domain\Support\ServiceClient\ConfigDB;
use App\Domain\Support\ServiceClient\Discovery;
use App\Domain\Support\ServiceClient\HTTP;

class ServiceClient {
    # I don't like this; but everything else seems to just use static
    # methods to get hold of singletons.
    private static ServiceClient $client;
    public static function get ()
    {
        self::$client ??= new ServiceClient();
        return self::$client;
    }
    public static function set (ServiceClient $cl)
    {
        self::$client = $cl;
    }

    # XXX This is crying out to be refactored, but I can't see how...
    
    private ConfigDB $configdb;
    public function configdb ()
    {
        $this->configdb ??= new ConfigDB($this);
        return $this->configdb;
    }

    private Discovery $discovery;
    public function discovery ()
    {
        $this->discovery ??= new Discovery($this);
        return $this->discovery;
    }

    private HTTP $http;
    public function http ()
    {
        $this->http ??= new HTTP($this);
        return $this->http;
    }
}
