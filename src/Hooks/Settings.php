<?php

namespace LibreNMS\Plugins\WeathermapNG\Hooks;

use Illuminate\Foundation\Auth\User;
use LibreNMS\Interfaces\Plugins\Hooks\SettingsHook;

class Settings implements SettingsHook
{
    public function authorize(User $user): bool
    {
        // Only admins can access plugin settings
        // Check various admin methods that may exist in LibreNMS User model
        if (method_exists($user, 'hasGlobalAdmin') && $user->hasGlobalAdmin()) {
            return true;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
        // Fallback: check level attribute (10 = admin in LibreNMS)
        if (isset($user->level) && $user->level >= 10) {
            return true;
        }
        return false;
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
