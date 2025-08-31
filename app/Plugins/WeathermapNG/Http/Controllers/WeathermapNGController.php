<?php

namespace App\Plugins\WeathermapNG\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class WeathermapNGController extends Controller
{
    /**
     * Display main plugin page
     * This is handled by the Page hook, but kept for direct route access
     */
    public function index(Request $request)
    {
        $maps = $this->getMaps();
        
        return view('weathermapng::page', [
            'title' => 'WeathermapNG - Network Weather Maps',
            'maps' => $maps,
            'request' => $request,
        ]);
    }

    /**
     * Show a specific map
     */
    public function show($id)
    {
        $map = $this->getMap($id);
        
        if (!$map) {
            abort(404, 'Map not found');
        }
        
        return view('weathermapng::map', [
            'map' => $map,
            'title' => $map->name . ' - WeathermapNG',
        ]);
    }

    /**
     * Show map editor
     */
    public function edit($id)
    {
        $map = $this->getMap($id);
        
        if (!$map) {
            abort(404, 'Map not found');
        }
        
        return view('weathermapng::editor', [
            'map' => $map,
            'title' => 'Edit ' . $map->name . ' - WeathermapNG',
        ]);
    }

    /**
     * Store a new map
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'width' => 'required|integer|min:400|max:2000',
            'height' => 'required|integer|min:300|max:1500',
        ]);
        
        try {
            $id = DB::table('wmng_maps')->insertGetId([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'width' => $validated['width'],
                'height' => $validated['height'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to create map'], 500);
        }
    }

    /**
     * Update a map
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'width' => 'required|integer|min:400|max:2000',
            'height' => 'required|integer|min:300|max:1500',
        ]);
        
        try {
            DB::table('wmng_maps')
                ->where('id', $id)
                ->update([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? '',
                    'width' => $validated['width'],
                    'height' => $validated['height'],
                    'updated_at' => now(),
                ]);
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update map'], 500);
        }
    }

    /**
     * Delete a map
     */
    public function destroy($id)
    {
        try {
            DB::table('wmng_maps')->where('id', $id)->delete();
            DB::table('wmng_nodes')->where('map_id', $id)->delete();
            DB::table('wmng_links')->where('map_id', $id)->delete();
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete map'], 500);
        }
    }

    /**
     * Get map data for live updates
     */
    public function data($id): JsonResponse
    {
        $map = $this->getMap($id);
        
        if (!$map) {
            return response()->json(['error' => 'Map not found'], 404);
        }
        
        $nodes = DB::table('wmng_nodes')->where('map_id', $id)->get();
        $links = DB::table('wmng_links')->where('map_id', $id)->get();
        
        // TODO: Add real-time data from LibreNMS
        
        return response()->json([
            'map' => $map,
            'nodes' => $nodes,
            'links' => $links,
            'timestamp' => now(),
        ]);
    }

    /**
     * Get list of devices
     */
    public function devices(): JsonResponse
    {
        try {
            $devices = DB::table('devices')
                ->select('device_id', 'hostname', 'sysName', 'type', 'status')
                ->orderBy('hostname')
                ->get();
            
            return response()->json(['devices' => $devices]);
        } catch (\Exception $e) {
            return response()->json(['devices' => []]);
        }
    }

    /**
     * Search devices
     */
    public function searchDevices(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        
        try {
            $devices = DB::table('devices')
                ->select('device_id', 'hostname', 'sysName')
                ->where('hostname', 'like', "%{$query}%")
                ->orWhere('sysName', 'like', "%{$query}%")
                ->limit(20)
                ->get();
            
            return response()->json(['devices' => $devices]);
        } catch (\Exception $e) {
            return response()->json(['devices' => []]);
        }
    }

    /**
     * Get ports for a device
     */
    public function ports($deviceId): JsonResponse
    {
        try {
            $ports = DB::table('ports')
                ->select('port_id', 'ifName', 'ifAlias', 'ifDescr', 'ifOperStatus')
                ->where('device_id', $deviceId)
                ->orderBy('ifName')
                ->get();
            
            return response()->json(['ports' => $ports]);
        } catch (\Exception $e) {
            return response()->json(['ports' => []]);
        }
    }

    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'plugin' => 'WeathermapNG',
            'version' => '2.0.0',
            'timestamp' => now(),
        ]);
    }

    /**
     * Ready check endpoint
     */
    public function ready(): JsonResponse
    {
        try {
            // Check database connection
            DB::connection()->getPdo();
            
            return response()->json([
                'status' => 'ready',
                'database' => 'connected',
                'timestamp' => now(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'not_ready',
                'database' => 'disconnected',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Helper: Get all maps
     */
    private function getMaps()
    {
        if (!$this->tablesExist()) {
            return collect([]);
        }
        
        try {
            return DB::table('wmng_maps')
                ->select('id', 'name', 'description', 'width', 'height', 'updated_at')
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Helper: Get single map
     */
    private function getMap($id)
    {
        if (!$this->tablesExist()) {
            return null;
        }
        
        try {
            return DB::table('wmng_maps')->where('id', $id)->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Helper: Check if tables exist
     */
    private function tablesExist()
    {
        try {
            return DB::getSchemaBuilder()->hasTable('wmng_maps');
        } catch (\Exception $e) {
            return false;
        }
    }
}