<?php

namespace App\Plugins\WeathermapNG;

use LibreNMS\Interfaces\Plugins\MenuEntryHook;

class Menu extends MenuEntryHook
{
    public string $view = 'weathermapng::menu';

    public function data(): array
    {
        return [
            'title' => 'WeathermapNG',
            'url' => url('/plugin/weathermapng'),
            'icon' => 'fa-map',
            'order' => 1000,
        ];
    }
}