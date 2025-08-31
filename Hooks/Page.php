<?php

namespace LibreNMS\Plugins\WeathermapNG\Hooks;

use App\Plugins\Hooks\PageHook;
use App\Models\User;
use LibreNMS\Plugins\WeathermapNG\Models\Map;

class Page extends PageHook
{
    /**
     * The main page view
     */
    public string $view = 'WeathermapNG::hooks.page';
    
    /**
     * Page title
     */
    public string $name = 'Network Weathermaps';
    
    /**
     * Provide data to the main page view
     */
    public function data(array $settings = []): array
    {
        $maps = [];
        $stats = [];
        
        try {
            // Get all maps
            $maps = Map::with(['nodes', 'links'])->get();
            
            // Calculate statistics
            $stats = [
                'total_maps' => $maps->count(),
                'total_nodes' => \LibreNMS\Plugins\WeathermapNG\Models\Node::count(),
                'total_links' => \LibreNMS\Plugins\WeathermapNG\Models\Link::count(),
                'last_updated' => Map::max('updated_at'),
            ];
        } catch (\Exception $e) {
            // Handle database errors
        }
        
        return [
            'title' => 'Network Weathermaps',
            'maps' => $maps,
            'stats' => $stats,
            'can_create' => auth()->user()->can('create-maps'),
        ];
    }
    
    /**
     * All authenticated users can view
     */
    public function authorize(User $user): bool
    {
        return true;
    }
}