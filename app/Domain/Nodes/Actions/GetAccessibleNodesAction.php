<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Nodes\Actions;

use App\Domain\Support\ServiceClient;
use App\Domain\Support\ServiceClient\UUIDs;
use App\Domain\Groups\Models\Group;
use App\Domain\Nodes\Models\Node;

use Illuminate\Support\Facades\Log;

class GetAccessibleNodesAction
{
    /**
     * This action gets all nodes that the user is authorised to view for the given group
     **/
    public function execute(string $group)
    {
        $fplus = ServiceClient::get();
        $cdb = $fplus->configdb();

        $nodes = $cdb->searchConfig(
            UUIDs\App::EdgeAgentDeployment,
            ["sparkplug.group" => $group],
            results: ["name" => "sparkplug.node_id"]);

        $searchTerm = request()->query('search');

        // If we have a search term then get all of the model IDs that match the search
        if ($searchTerm) {
            $nodes = array_filter(fn($n) => stristr($n->name, $searchTerm), $nodes);
        }

//        // If the user is not an administrator then they can only see their accessible nodes
//        if (! auth()->user()->administrator) {
//            $query = auth()->user()->accessibleNodes();
//        } else {
//            $query = Node::query();
//        }

        $rv = [];
        foreach ($nodes as $uuid => $props) {
            $rv[] = ["id" => $uuid, "node_id" => $props["name"]];
        }

        return action_success($rv);
    }
}
