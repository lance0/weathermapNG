<?php

namespace LibreNMS\Plugins\WeathermapNG\Hooks;

use App\Plugins\Hooks\MenuEntryHook;
use App\Models\User;

class Menu extends MenuEntryHook
{
    /**
     * The menu entry name - appears in the menu
     */
    public string $view = 'WeathermapNG::hooks.menu';
    
    /**
     * Provide data to the menu view
     */
    public function data(array $settings = []): array
    {
        // Get map count for badge display
        $mapCount = 0;
        try {
            $mapCount = \LibreNMS\Plugins\WeathermapNG\Models\Map::count();
        } catch (\Exception $e) {
            // Database might not be set up yet
        }
        
        return [
            'title' => 'WeathermapNG',
            'url' => url('/plugins/weathermapng'),
            'icon' => 'fa-map',
            'map_count' => $mapCount,
        ];
    }
    
    /**
     * Control when the menu item is shown
     */
    public function authorize(User $user): bool
    {
        // Show to all authenticated users
        // You could add role checks here: return $user->can('view-maps');
        return true;
    }
}