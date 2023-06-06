<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Support\ServiceClient;

use App\Domain\Auth\Actions\GetServiceTokenAction;
use App\Domain\Support\ServiceClient;
use App\Exceptions\ActionErrorException;
use Illuminate\Support\Facades\Http as IlHttp;
use Illuminate\Support\Facades\Log;

class HTTP 
{
    public function __construct (public ServiceClient $client)
    { }

    public function fetch(string $type, string $service, string $url, $payload = null, $file = null)
    {
        // Validate the request
        if (! in_array($type, ['get', 'post', 'put'])) {
            throw new ServiceClientException('Incorrect method passed to Fetch');
        }

        $base = $this->client->discovery()->serviceUrl($service);
        $url = $base . $url;

        // Try the request with the cached token for the service
        $response = $this->do($type, $service, $url, $payload, $file, false);

        // If response failed because of an expired bearer token then get a new one
        if ($response->unauthorized()) {
            Log::debug('Refreshing token for ' . $service . ' after failed auth.');
            $response = $this->do($type, $service, $url, $payload, $file, true);
        }

        if ($response->failed()) {
            throw new ServiceClientException('Failed to communicate with ' . $service . '. Status: ' . $response->status() . '.');
        }

        return $response;
    }

    public function do(string $type, string $service, string $url, $payload = null, $file = null, $force = false)
    {
        $base = IlHttp::withToken((new GetServiceTokenAction)->execute($service, $force)['data']);

        if ($file) {
            $base = $base->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName(), [
                'Content-Type' => $file->getClientMimeType(),
            ])->asMultipart();
        }

        return $base->$type($url, $payload);
    }
}
