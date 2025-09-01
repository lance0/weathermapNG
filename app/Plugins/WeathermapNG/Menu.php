<?php

namespace App\Plugins\WeathermapNG;

use LibreNMS\Plugins\Hooks\MenuEntryHook;

class Menu extends MenuEntryHook
{
    public string $view = 'weathermapng::menu';
    public function data(): array
    {
        return [
            'title' => 'WeathermapNG',
            'url' => route('plugin.page', ['plugin' => 'WeathermapNG']),
            'icon' => 'fa-network-wired',
        ];
    }

    public function authorize($user): bool
    {
        // Allow all authenticated users to see the menu
        return $user !== null;
    }
}