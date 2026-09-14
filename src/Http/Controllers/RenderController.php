<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Services\MapService;
use LibreNMS\Plugins\WeathermapNG\Services\LegacyConfService;
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

    public function embed(Map $map, Request $request): View
    {
        $map->load(['nodes', 'links']);
        $this->nodeDataService->preloadForMap($map);
        $mapData = $map->toJsonModel();
        $mapId = $map->id;

        // Include initial live data so page renders with traffic immediately
        $liveData = [
            'links' => $this->nodeDataService->buildLinkData($map),
            'nodes' => $this->nodeDataService->buildNodeData($map),
            'alerts' => $this->nodeDataService->buildAlertData($map),
        ];

        $demoMode = config('weathermapng.demo_mode', false);

        $kiosk = $request->boolean('kiosk');
        $cycle = $request->input('cycle');
        $cycleSeconds = ctype_digit((string) $cycle) ? max(5, (int) $cycle) : null;
        $target = in_array($request->input('target'), ['self', '_self'], true) ? '_self' : '_blank';

        // Ordered list of maps so kiosk mode can cycle without an extra API call.
        // Only build it when kiosk cycling is actually active.
        $mapList = ($kiosk === true && $cycleSeconds !== null)
            ? Map::query()
                ->select(['id', 'name', 'title'])
                ->orderBy('name')
                ->get()
                ->map(fn($m) => ['id' => $m->id, 'name' => $m->name, 'title' => $m->title])
                ->values()
                ->toArray()
            : [];

        return view('WeathermapNG::embed', compact(
            'mapData',
            'mapId',
            'liveData',
            'demoMode',
            'kiosk',
            'cycleSeconds',
            'target',
            'mapList'
        ));
    }

    public function export(Map $map, Request $request): JsonResponse
    {
        $format = $request->get('format', 'json');

        if ($format === 'json') {
            $map->load(['nodes', 'links']);
            // Strip CR/LF, quotes, backslashes, and path separators so the
            // map name cannot inject response headers or traverse paths.
            $safeName = preg_replace('/[\r\n"\0\\\\\/]+/', '_', $map->name ?? 'map');
            $safeName = trim($safeName, " \t._-") ?: 'map';
            return response()->json($map->toJsonModel())
                           ->header('Content-Disposition', 'attachment; filename="' . $safeName . '.json"');
        }

        if ($format === 'conf') {
            $map->load(['nodes', 'links']);
            $safeName = preg_replace('/[\r\n"\0\\\\\/]+/', '_', $map->name ?? 'map');
            $safeName = trim($safeName, " \t._-") ?: 'map';
            $conf = (new LegacyConfService())->toConf($map);

            return response($conf, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $safeName . '.conf"',
            ]);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'file' => 'required|file|mimes:json,txt,conf|max:10240',
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
        $interval = min(60, max(1, (int) $request->get('interval', 5)));
        $maxSeconds = min(600, max(5, (int) $request->get('max', 300)));

        return $this->nodeDataService->stream($map, $interval, $maxSeconds);
    }
}
