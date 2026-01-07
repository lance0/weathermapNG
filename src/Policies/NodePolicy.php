<?php

namespace LibreNMS\Plugins\WeathermapNG\Policies;

use LibreNMS\Plugins\WeathermapNG\Models\Node;

class NodePolicy
{
    public function view($user, Node $node): bool
    {
        return true;
    }

    public function create($user): bool
    {
        return true;
    }

    public function update($user, Node $node): bool
    {
        if ($user->isAdmin ?? false) {
            return true;
        }

        $map = $node->map;
        return $map->user_id === $user->id ?? null;
    }

    public function delete($user, Node $node): bool
    {
        if ($user->isAdmin ?? false) {
            return true;
        }

        $map = $node->map;
        return $map->user_id === $user->id ?? null;
    }

    public function manage($user, Node $node): bool
    {
        return $this->update($user, $node);
    }
}
