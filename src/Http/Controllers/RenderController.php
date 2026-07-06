<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Services\MapService;
use LibreNMS\Plugins\WeathermapNG\AdminCheck;
use LibreNMS\Plugins\WeathermapNG\Services\NodeDataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RenderController
{
    use AdminCheck;

    private $nodeDataService;
    private $mapService;

    public function __construct(NodeDataService $nodeDataService, MapService $mapService)
    {
        $this->nodeDataService = $nodeDataService;
        $this->mapService = $mapService;
    }

    public function json(Map $map): JsonResponse
    {
        return response()->json($map->toJsonModel());
    }

    public function live(Map $map): JsonResponse
    {
        $map->load(['nodes', 'links']);
        $this->nodeDataService->preloadForMap($map);
        $data = [
            'ts' => time(),
            'links' => $this->nodeDataService->buildLinkData($map),
            'nodes' => $this->nodeDataService->buildNodeData($map),
            'alerts' => $this->nodeDataService->buildAlertData($map),
        ];

        return response()->json($data);
    }

    public function embed(Map $map): View
    {
        $map->load(['nodes', 'links']);
        $this->nodeDataService->preloadForMap($map);
        $mapData = $map->toJsonModel();
        $mapId = $map->id;

        // Include initial live data so page renders with traffic immediately
        $liveData = [
            'links' => $this->nodeDataService->buildLinkData($map),
            'nodes' => $this->nodeDataService->buildNodeData($map),
        ];

        $demoMode = config('weathermapng.demo_mode', false);

        return view('WeathermapNG::embed', compact('mapData', 'mapId', 'liveData', 'demoMode'));
    }

    public function export(Map $map, Request $request): JsonResponse
    {
        $format = $request->get('format', 'json');

        if ($format === 'json') {
            $map->load(['nodes', 'links']);
            return response()->json($map->toJsonModel())
                           ->header('Content-Disposition', 'attachment; filename="' . $map->name . '.json"');
        }

        return response()->json(['error' => 'Unsupported format'], 400);
    }

    public function import(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'file' => 'required|file|mimes:json|max:10240',
            'name' => 'required|string|max:255|unique:wmng_maps,name',
            'title' => 'nullable|string|max:255',
        ]);

        try {
            $map = $this->mapService->importMap($request, $validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'map_id' => $map->id,
            'message' => 'Map imported successfully'
        ]);
    }

    public function sse(Map $map, Request $request): StreamedResponse
    {
        $map->load(['nodes', 'links']);
        $this->nodeDataService->preloadForMap($map);
        $interval = max(1, (int) $request->get('interval', 5));
        $maxSeconds = (int) $request->get('max', 300);  // 5 minutes default

        return $this->nodeDataService->stream($map, $interval, $maxSeconds);
    }
}
