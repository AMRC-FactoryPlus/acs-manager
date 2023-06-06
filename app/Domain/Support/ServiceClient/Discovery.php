<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Support\ServiceClient;

use League\Uri\Uri;

# This class does static discovery (preconfigured) for now. It could be
# extended later to discover via the Directory.
class Discovery extends ServiceInterface
{
    public function serviceUrl (string $service)
    {
        return Uri::createFromString($this->lookup($service));
    }

    public function lookup (string $service)
    {
        switch ($service) {
            case "auth":
                return config('manager.auth_service_url');
            case "cmdesc":
                return config('manager.cmdesc_service_url');
            case "configdb":
                return config('manager.configdb_service_url');
            case "file-service":
                return config('manager.file_service_url');
            default:
                throw new ServiceClientException(
                    sprintf("Unknown service for discovery: %s", $service));
        }
    }
}

?>
