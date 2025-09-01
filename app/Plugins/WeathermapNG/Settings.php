<?php

namespace App\Plugins\WeathermapNG;

use App\Plugins\Hooks\SettingsHook;

class Settings extends SettingsHook
{
    public string $view = 'weathermapng::settings';

    public function data(array $settings = []): array
    {
        return [
            'title' => 'WeathermapNG Settings',
            'settings' => $settings,
        ];
    }
}
