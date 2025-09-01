<?php

namespace LibreNMS\Plugins\WeathermapNG\Hooks;

use LibreNMS\Interfaces\Plugins\Hooks\MenuEntryHook;

class MenuEntry implements MenuEntryHook
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function handle(string $pluginName): array
    {
        $mapCount = 0;
        try {
            if (class_exists('\LibreNMS\Plugins\WeathermapNG\Models\Map')) {
                $mapCount = \LibreNMS\Plugins\WeathermapNG\Models\Map::count();
            } elseif (class_exists('\Illuminate\Support\Facades\DB')) {
                $mapCount = \Illuminate\Support\Facades\DB::table('wmng_maps')->count();
            }
        } catch (\Exception $e) {
            // Tables might not exist yet
        }

        // Return view name with plugin namespace and data
        return ["{$pluginName}::menu", [
            'title' => 'Network Maps',
            'url' => url('plugin/WeathermapNG'),
            'icon' => 'fa-network-wired',
            'badge' => $mapCount > 0 ? $mapCount : null,
        ]];
    }
}