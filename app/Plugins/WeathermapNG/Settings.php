<?php

namespace App\Plugins\WeathermapNG;

use App\Plugins\Hooks\SettingsHook;

class Settings extends SettingsHook
{
    // Use relative view path; core will prefix with 'WeathermapNG::'
    public string $view = 'resources.views.settings';

    public function data(array $settings = []): array
    {
        return [
            'title' => 'WeathermapNG Settings',
            'settings' => $settings,
        ];
    }
}
