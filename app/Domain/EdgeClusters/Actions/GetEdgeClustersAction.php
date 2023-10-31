<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\EdgeClusters\Actions;

use App\Domain\Support\Actions\MakeConsumptionFrameworkRequest;
use App\Exceptions\ActionForbiddenException;
use function func_get_args;

class GetEdgeClustersAction
{

    /**
     * This action
     **/

    private function authorise()
    {
        if ((!auth()->user()->administrator)) {
            throw new ActionForbiddenException('You do not have permission to view remote clusters.');
        }
    }

    private function validate()
    {
    }

    public function execute()
    {

        // Validate and authorise the request
        $this->authorise(...func_get_args());
        $this->validate(...func_get_args());

        // First get all of the edge clusters
        $response = (new MakeConsumptionFrameworkRequest)->execute(type: 'get', service: 'configdb', url: config('manager.configdb_service_url') . '/v1/app/747a62c9-1b66-4a2e-8dd9-0b70a91b6b75/object',)['data'];

        $edgeClusters = json_decode($response->body());

        $clusters = [];

        // Iterate through the edge clusters
        foreach ($edgeClusters as $cluster) {
            // Then hit the general object information endpoint for each cluster to get its name
            $clusterName = (new MakeConsumptionFrameworkRequest)->execute(type: 'get', service: 'configdb', url: config('manager.configdb_service_url') . '/v1/app/64a8bfa9-7772-45c4-9d1a-9e6290690957/object/' . $cluster,)['data'];
            // Then hit the edge cluster status endpoint for each cluster to get its status and nodes
            $clusterResponse = (new MakeConsumptionFrameworkRequest)->execute(type: 'get', service: 'configdb', url: config('manager.configdb_service_url') . '/v1/app/747a62c9-1b66-4a2e-8dd9-0b70a91b6b75/object/' . $cluster,)['data'];

            $clusters[$clusterName['name']] = [
                'uuid' => $cluster,
                'nodes' => json_decode($clusterResponse->body())->hosts,
                ];

        }

        return action_success($clusters);
    }

}
