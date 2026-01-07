<?php

namespace LibreNMS\Plugins\WeathermapNG\Policies;

use LibreNMS\Plugins\WeathermapNG\Models\Map;

class MapPolicy
{
    public function view($user, Map $map): bool
    {
        return true;
    }

    public function create($user): bool
    {
        return true;
    }

    public function update($user, Map $map): bool
    {
        if ($user->isAdmin ?? false) {
            return true;
        }

        return $map->user_id === $user->id ?? null;
    }

    public function delete($user, Map $map): bool
    {
        if ($user->isAdmin ?? false) {
            return true;
        }

        return $map->user_id === $user->id ?? null;
    }

    public function manage($user, Map $map): bool
    {
        return $this->update($user, $map);
    }
}
