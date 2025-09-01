<?php

namespace App\Plugins\WeathermapNG;

use App\Models\Port;
use App\Plugins\Hooks\PortTabHook;

class PortTab extends PortTabHook
{
    public string $view = 'resources.views.port-tab';

    public function data(Port $port): array
    {
        return [
            'title' => 'WeathermapNG',
            'port' => $port,
            'plugin_url' => url('plugin/WeathermapNG'),
        ];
    }
}

