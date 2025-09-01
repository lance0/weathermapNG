<?php

namespace App\Plugins\WeathermapNG;

use App\Plugins\Hooks\MenuEntryHook;

class Menu extends MenuEntryHook
{
    public string $view = 'resources.views.menu';

    public function data(): array
    {
        return [
            'title' => 'WeathermapNG',
            'url' => url('plugin/WeathermapNG'),
            'icon' => 'fa-network-wired',
        ];
    }
}
