<?php

namespace LibreNMS\Plugins\WeathermapNG\Hooks;

use Illuminate\Foundation\Auth\User;
use LibreNMS\Interfaces\Plugins\Hooks\PageHook;

class Page implements PageHook
{
    public function authorize(User $user): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(string $pluginName): array
    {
        $maps = [];
        try {
            if (class_exists('\Illuminate\Support\Facades\DB')) {
                $maps = \Illuminate\Support\Facades\DB::table('wmng_maps')
                    ->select('wmng_maps.*')
                    ->selectRaw('(SELECT COUNT(*) FROM wmng_nodes WHERE map_id = wmng_maps.id) as nodes_count')
                    ->selectRaw('(SELECT COUNT(*) FROM wmng_links WHERE map_id = wmng_maps.id) as links_count')
                    ->orderBy('name')
                    ->get();
            }
        } catch (\Exception $e) {
            // Tables might not exist yet
        }

        return [
            'content_view' => "WeathermapNG::page",
            'title' => 'Network Weather Maps',
            'maps' => $maps,
            'can_create' => true,
        ];
    }
}