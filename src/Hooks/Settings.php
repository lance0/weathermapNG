<?php

namespace LibreNMS\Plugins\WeathermapNG\Hooks;

use Illuminate\Foundation\Auth\User;
use LibreNMS\Interfaces\Plugins\Hooks\SettingsHook;

class Settings implements SettingsHook
{
    public function authorize(User $user): bool
    {
        return true;
    }

    /**
     * @param  array<string, array<string, mixed>>  $settings
     * @return array<string, mixed>
     */
    public function handle(string $pluginName, array $settings): array
    {
        return [
            'content_view' => "{$pluginName}::settings",
            'settings' => $settings,
        ];
    }
}