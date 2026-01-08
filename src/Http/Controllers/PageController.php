<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Facades\Redirect;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use Exception;

class PageController extends Controller
{
    /**
     * Display main plugin page
     */
    public function index(): View
    {
        // Check if database tables exist, redirect to installer if not
        if (!$this->isInstalled()) {
            return redirect()->route('weathermapng.install');
        }

        // Get all maps with counts using Eloquent for proper model features
        $maps = Map::withCount(['nodes', 'links'])->orderBy('name')->get();

        return view('WeathermapNG::index', compact('maps'));
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
     * Display view page - redirects to embed for live visualization
     */
    public function view($mapId)
    {
        $map = DB::table('wmng_maps')->find($mapId);

        if (!$map) {
            abort(404, 'Map not found');
        }

        return redirect()->route('weathermapng.embed', ['map' => $mapId]);
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

    /**
     * Check if plugin is installed (database tables exist)
     */
    private function isInstalled(): bool
    {
        try {
            $tables = DB::select("SHOW TABLES LIKE 'wmng_%'");
            return count($tables) >= 3;
        } catch (Exception $e) {
            return false;
        }
    }
}
