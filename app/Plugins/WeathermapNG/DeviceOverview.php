<?php

namespace App\Plugins\WeathermapNG;

use App\Models\Device;
use App\Plugins\Hooks\DeviceOverviewHook;

class DeviceOverview extends DeviceOverviewHook
{
    public string $view = 'resources.views.device-overview';

    public function data(Device $device): array
    {
        return [
            'title' => 'WeathermapNG',
            'device' => $device,
            'plugin_url' => url('plugin/WeathermapNG'),
        ];
    }
}

