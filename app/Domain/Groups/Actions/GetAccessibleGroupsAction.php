<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Groups\Actions;

use App\Domain\Support\ServiceClient;
use App\Domain\Support\ServiceClient\UUIDs;
use App\Domain\Groups\Models\Group;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

class GetAccessibleGroupsAction
{
    /**
     * This action gets all the groups that contain nodes that the current user has permission to access
     **/
    public function execute()
    {
        $fplus = ServiceClient::get();
        $cdb = $fplus->configdb();

        $groups = $cdb->searchConfig(
            UUIDs\App::SparkplugAddress, [],
            class: UUIDs\Klass::SparkplugGroup,
            results: ["name" => "group_id"]);

        $searchTerm = request()->query('search');
        if ($searchTerm) {
            $groups = array_filter($groups,
                fn($g) => stristr($g["name"], $searchTerm) !== false);
        }

//        $user = auth()->user();
//        $acc_grps = $user->accessibleNodes->pluck("group.id")->toArray();
//        Log::debug("Accessible groups", $acc_grps);

        $rv = [];
        foreach ($groups as $uuid => $props) {
            $rv[] = ["id" => $uuid, "name" => $props["name"]];
        }

        return action_success($rv);

//        Group::
//        // If we have a search term then apply then filter only the models that were returned from the search
//        when($searchTerm !== null, function ($query) use ($modelIDs) {
//            $query->whereIn('id', $modelIDs);
//        })
//            // If we are not an administrator then filter only the groups for nodes that we have access to
//            ->when(! auth()->user()->administrator, function ($query) {
//                $query->whereIn('id', auth()->user()->accessibleNodes->pluck('group.id'));
//            })
//            ->with('cluster')
//            // Return the count of the nodes with the result (excluding any bridges)
//            ->withCount([
//                'nodes' => function (Builder $query) {
//                    $query->whereNotNull('node_id');
//                },
//            ]));
    }
}
