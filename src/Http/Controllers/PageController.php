<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * Display main plugin page
     */
    public function index(): View
    {
        // Get all maps with counts
        $maps = DB::table('wmng_maps')
            ->select('wmng_maps.*')
            ->selectRaw('(SELECT COUNT(*) FROM wmng_nodes WHERE map_id = wmng_maps.id) as nodes_count')
            ->selectRaw('(SELECT COUNT(*) FROM wmng_links WHERE map_id = wmng_maps.id) as links_count')
            ->orderBy('name')
            ->get();

        return view('WeathermapNG::page', [
            'title' => 'Network Weather Maps',
            'maps' => $maps,
            'can_create' => true,
        ]);
    }

    /**
     * Display editor page
     */
    public function editor($mapId = null): View
    {
        $map = null;
        if ($mapId) {
            $map = DB::table('wmng_maps')->find($mapId);
        }

        return view('WeathermapNG::editor', [
            'title' => $map ? 'Edit Map: ' . $map->name : 'Create New Map',
            'map' => $map,
            'mapId' => $mapId,
        ]);
    }

    /**
     * Display view page
     */
    public function view($mapId): View
    {
        $map = DB::table('wmng_maps')->find($mapId);

        if (!$map) {
            abort(404, 'Map not found');
        }

        return view('WeathermapNG::view', [
            'title' => 'View Map: ' . $map->name,
            'map' => $map,
            'mapId' => $mapId,
        ]);
    }

    /**
     * Display settings page
     */
    public function settings(): View
    {
        return view('WeathermapNG::settings', [
            'title' => 'WeathermapNG Settings',
            'settings' => config('weathermapng'),
        ]);
    }
}
