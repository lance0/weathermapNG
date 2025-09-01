<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * Display the main plugin page
     */
    public function index(Request $request)
    {
        // Get all maps with counts
        $maps = \Illuminate\Support\Facades\DB::table('wmng_maps')
            ->select('wmng_maps.*')
            ->selectRaw('(SELECT COUNT(*) FROM wmng_nodes WHERE map_id = wmng_maps.id) as nodes_count')
            ->selectRaw('(SELECT COUNT(*) FROM wmng_links WHERE map_id = wmng_maps.id) as links_count')
            ->orderBy('name')
            ->get();

        return view('weathermapng::page', [
            'title' => 'Network Weather Maps',
            'maps' => $maps,
            'can_create' => true,
        ]);
    }

    /**
     * Display the editor page
     */
    public function editor(Request $request, $id = null)
    {
        $map = null;
        if ($id) {
            $map = \Illuminate\Support\Facades\DB::table('wmng_maps')->find($id);
        }

        return view('weathermapng::editor', [
            'title' => $map ? 'Edit Map: ' . $map->name : 'Create New Map',
            'map' => $map,
            'mapId' => $id,
        ]);
    }

    /**
     * Display the view page
     */
    public function view(Request $request, $id)
    {
        $map = \Illuminate\Support\Facades\DB::table('wmng_maps')->find($id);
        
        if (!$map) {
            abort(404, 'Map not found');
        }

        return view('weathermapng::view', [
            'title' => 'View Map: ' . $map->name,
            'map' => $map,
            'mapId' => $id,
        ]);
    }

    /**
     * Display the settings page
     */
    public function settings(Request $request)
    {
        return view('weathermapng::settings', [
            'title' => 'WeathermapNG Settings',
            'settings' => config('weathermapng'),
        ]);
    }
}