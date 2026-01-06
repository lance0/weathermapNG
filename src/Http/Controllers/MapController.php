<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Services\MapService;
use LibreNMS\Plugins\WeathermapNG\Services\AutoDiscoveryService;
use Illuminate\Http\Request;

class MapController
{
    private $mapService;
    private $autoDiscoveryService;

    public function __construct(
        MapService $mapService,
        AutoDiscoveryService $autoDiscoveryService
    ) {
        $this->mapService = $mapService;
        $this->autoDiscoveryService = $autoDiscoveryService;
    }

    public function index()
    {
        $maps = Map::withCount(['nodes', 'links'])->get();
        return view('WeathermapNG::index', compact('maps'));
    }

    public function show(Map $map)
    {
        return redirect()->route('weathermapng.embed', $map);
    }

    public function editor(Map $map)
    {
        $map->load(['nodes', 'links']);
        $devices = $this->getDevicesForEditor();

        return view('WeathermapNG::editor', compact('map', 'devices'));
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:wmng_maps,name',
            'title' => 'nullable|string|max:255',
            'width' => 'nullable|integer|min:100|max:4096',
            'height' => 'nullable|integer|min:100|max:4096',
        ]);

        $map = $this->mapService->createMap($validated);

        return $this->handleCreateResponse($request, $map);
    }

    public function update(Request $request, Map $map)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'width' => 'nullable|integer|min:100|max:4096',
            'height' => 'nullable|integer|min:100|max:4096',
            'background' => 'nullable|string',
        ]);

        $this->mapService->updateMap($map, $validated);

        return response()->json(['success' => true, 'map' => $map]);
    }

    public function destroy(Map $map)
    {
        $this->mapService->deleteMap($map);

        return $this->handleDeleteResponse();
    }

    public function save(Request $request, Map $map)
    {
        $validatedData = $this->validateSaveRequest($request);

        try {
            $this->mapService->saveMap($map, $validatedData);
            return response()->json(['success' => true, 'message' => 'Map saved successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save map: ' . $e->getMessage()
            ], 500);
        }
    }

    public function autoDiscover(Request $request, Map $map)
    {
        $params = $this->autoDiscoveryService->validateDiscoveryParams($request->all());

        try {
            $this->autoDiscoveryService->discoverAndSeedMap($map, $params);
            return response()->json(['success' => true, 'message' => 'Auto-discovery completed']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auto-discovery failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function validateSaveRequest(Request $request): array
    {
        return $request->validate([
            'title' => 'nullable|string|max:255',
            'options' => 'array',
            'options.width' => 'nullable|integer|min:100|max:4096',
            'options.height' => 'nullable|integer|min:100|max:4096',
            'options.background' => 'nullable|string',
            'nodes' => 'array',
        ]);
    }

    private function getDevicesForEditor()
    {
        try {
            if (class_exists('\App\Models\Device')) {
                return \App\Models\Device::select('device_id', 'hostname', 'sysName')
                    ->where('disabled', 0)
                    ->where('ignore', 0)
                    ->orderBy('hostname')
                    ->get();
            }

            $devices = dbFetchRows(
                "SELECT device_id, hostname, sysName\n" .
                "FROM devices\n" .
                "WHERE disabled = 0 AND ignore = 0\n" .
                "ORDER BY hostname"
            );

            return collect($devices)->map(fn($device) => (object) $device);
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    private function handleCreateResponse(Request $request, Map $map)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Map created successfully!',
                'map' => $map,
                'redirect' => route('weathermapng.editor', $map)
            ]);
        }

        return redirect()->route('weathermapng.editor', $map)
            ->with('success', 'Map created successfully!');
    }

    private function handleDeleteResponse()
    {
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Map deleted successfully!'
            ]);
        }

        return redirect()->route('weathermapng.index')
            ->with('success', 'Map deleted successfully!');
    }
}
