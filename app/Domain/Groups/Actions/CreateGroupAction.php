<?php

    /*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace App\Domain\Groups\Actions;

    use App\Domain\Clusters\Models\Cluster;
    use App\Domain\Groups\Models\Group;
    use App\Exceptions\ActionFailException;
    use App\Exceptions\ActionForbiddenException;

    class CreateGroupAction
    {
        /**
         * This action creates a new group
         **/
        /*
         * Constraints:
         * - The user must be an admin
         */

        public function execute(string $name, Cluster $cluster)
        {
            // =========================
            // Validate User Permissions
            // =========================

            if (! auth()->user()->administrator) {
                throw new ActionForbiddenException('Only administrators can create new groups.');
            }

            $reg = '/^' . $cluster->name . '-\w+-\w+(:?-\d+)?$/i';

            if (! preg_match($reg, $name)) {
                throw new ActionFailException('The name format is invalid.');
            }

            // ===================
            // Perform the Action
            // ===================

            return action_success(Group::create([
                'name' => $name,
                'cluster_id' => $cluster->id,
            ]));
        }
    }
