<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Services\MapService;
use LibreNMS\Plugins\WeathermapNG\Services\AutoDiscoveryService;
use LibreNMS\Plugins\WeathermapNG\Http\Requests\CreateMapRequest;
use LibreNMS\Plugins\WeathermapNG\Http\Requests\UpdateMapRequest;

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

    public function index(): \Illuminate\View\View
    {
        $maps = Map::withCount(['nodes', 'links'])->get();
        return view('WeathermapNG::index', compact('maps'));
    }

    public function show(Map $map): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('weathermapng.embed', $map);
    }

    public function editor(Map $map): \Illuminate\View\View
    {
        $map->load(['nodes', 'links']);
        $devices = $this->getDevicesForEditor();

        return view('WeathermapNG::editor', compact('map', 'devices'));
    }

    public function create(CreateMapRequest $request): mixed
    {
        $validated = $request->sanitize($request->validated());
        $map = $this->mapService->createMap($validated);

        return $this->handleCreateResponse($request, $map);
    }

    public function update(UpdateMapRequest $request, Map $map): \Illuminate\Http\JsonResponse
    {
        $validated = $request->sanitize($request->validated());
        $this->mapService->updateMap($map, $validated);

        return response()->json([
            'success' => true,
            'map' => $map,
        ]);
    }

    public function destroy(Map $map): mixed
    {
        $this->mapService->deleteMap($map);

        return $this->handleDeleteResponse();
    }

    public function save(\Illuminate\Http\Request $request, Map $map): \Illuminate\Http\JsonResponse
    {
        $validatedData = $this->validateSaveRequest($request->all());

        try {
            $this->mapService->saveMap($map, $validatedData);
            return response()->json([
                'success' => true,
                'message' => 'Map saved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save map: ' . $e->getMessage()
            ], 500);
        }
    }

    public function autoDiscover(\Illuminate\Http\Request $request, Map $map): \Illuminate\Http\JsonResponse
    {
        $params = $this->autoDiscoveryService->validateDiscoveryParams($request->all());

        try {
            $this->autoDiscoveryService->discoverAndSeedMap($map, $params);
            return response()->json([
                'success' => true,
                'message' => 'Auto-discovery completed',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auto-discovery failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function validateSaveRequest(array $data): array
    {
        return $data;
    }

    private function getDevicesForEditor(): \Illuminate\Support\Collection
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

    private function handleCreateResponse(\Illuminate\Http\Request $request, Map $map): mixed
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'map' => $map,
                'redirect' => route('weathermapng.editor', $map)
            ]);
        }

        return redirect()->route('weathermapng.editor', $map)
            ->with('success', 'Map created successfully!');
    }

    private function handleDeleteResponse(): mixed
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
