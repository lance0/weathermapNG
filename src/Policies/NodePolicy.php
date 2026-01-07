<?php

namespace LibreNMS\Plugins\WeathermapNG\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use LibreNMS\Plugins\WeathermapNG\Models\Node;

class NodePolicy
{
    use HandlesAuthorization;

    public function view($user, Node $node): bool
    {
        return true; // Authenticated users can view nodes
    }

    public function create($user): bool
    {
        return true; // Authenticated users can create nodes
    }

    public function update($user, Node $node): bool
    {
        if ($user->isAdmin ?? false) {
            return true;
        }

        $map = $node->map;
        return $map->user_id === ($user->id ?? null);
    }

    public function delete($user, Node $node): bool
    {
        if ($user->isAdmin ?? false) {
            return true;
        }

        $map = $node->map;
        return $map->user_id === ($user->id ?? null);
    }
}
