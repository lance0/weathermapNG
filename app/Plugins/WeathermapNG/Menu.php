<?php

namespace App\Plugins\WeathermapNG;

use App\Plugins\Hooks\MenuEntryHook;

class Menu extends MenuEntryHook
{
    // Use relative view path; core will prefix with 'WeathermapNG::'
    public string $view = 'resources.views.menu';

    public function data(): array
    {
        // Count maps for badge display
        $mapCount = 0;
        try {
            if (class_exists('\App\Models\Plugin\WeathermapNG\Map')) {
                $mapCount = \App\Models\Plugin\WeathermapNG\Map::count();
            } elseif (function_exists('dbFetchCell')) {
                $mapCount = dbFetchCell("SELECT COUNT(*) FROM wmng_maps") ?: 0;
            }
        } catch (\Exception $e) {
            // Silently fail if tables don't exist yet
        }

        return [
            'title' => 'Network Maps',
            'url' => url('plugin/WeathermapNG'),
            'icon' => 'fa-network-wired',
            'badge' => $mapCount > 0 ? $mapCount : null,
        ];
    }

    public function authorize(\App\Models\User $user): bool
    {
        // Allow all authenticated users to see the menu
        return true;
    }
}
