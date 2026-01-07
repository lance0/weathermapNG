<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Services\NodeDataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RenderController
{
    private $nodeDataService;

    public function __construct(NodeDataService $nodeDataService)
    {
        $this->nodeDataService = $nodeDataService;
    }

    public function json(Map $map): JsonResponse
    {
        return response()->json($map->toJsonModel());
    }

    public function live(Map $map): JsonResponse
    {
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
            return response()->json($map->toJsonModel())
                           ->header('Content-Disposition', 'attachment; filename="' . $map->name . '.json"');
        }

        return response()->json(['error' => 'Unsupported format'], 400);
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:json|max:10240',
            'name' => 'required|string|max:255|unique:wmng_maps,name',
            'title' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        if (!$file) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true);

        if (!$data || !isset($data['nodes']) || !isset($data['links'])) {
            return response()->json(['error' => 'Invalid map file format'], 400);
        }

        $map = $this->createMapFromImport($validated, $data);
        $nodeIdMap = $this->importNodes($map, $data['nodes']);
        $this->importLinks($map, $data['links'], $nodeIdMap);

        return response()->json([
            'success' => true,
            'map_id' => $map->id,
            'message' => 'Map imported successfully'
        ]);
    }

    public function sse(Map $map, Request $request): StreamedResponse
    {
        $interval = max(1, (int) $request->get('interval', 5));
        $maxSeconds = (int) $request->get('max', 60);

        return $this->nodeDataService->stream($map, $interval, $maxSeconds);
    }

    private function createMapFromImport(array $validated, array $data): Map
    {
        return Map::create([
            'name' => $validated['name'],
            'title' => $validated['title'] ?? $validated['name'],
            'options' => $data['options'] ?? [],
        ]);
    }

    private function importNodes(Map $map, array $nodes): array
    {
        $nodeIdMap = [];

        foreach ($nodes as $nodeData) {
            $new = \LibreNMS\Plugins\WeathermapNG\Models\Node::create([
                'map_id' => $map->id,
                'label' => $nodeData['label'] ?? ('node-' . uniqid()),
                'x' => $nodeData['x'] ?? 0,
                'y' => $nodeData['y'] ?? 0,
                'device_id' => $nodeData['device_id'] ?? null,
                'meta' => $nodeData['meta'] ?? [],
            ]);

            $oldId = $nodeData['id'] ?? $nodeData['node_id'] ?? null;
            if ($oldId !== null) {
                $nodeIdMap[$oldId] = $new->id;
            }
        }

        return $nodeIdMap;
    }

    private function importLinks(Map $map, array $links, array $nodeIdMap): void
    {
        foreach ($links as $linkData) {
            $sourceId = $this->resolveNodeId($linkData, $nodeIdMap, 'src');
            $targetId = $this->resolveNodeId($linkData, $nodeIdMap, 'dst');

            if (!$sourceId || !$targetId) {
                continue;
            }

            \LibreNMS\Plugins\WeathermapNG\Models\Link::create([
                'map_id' => $map->id,
                'src_node_id' => $sourceId,
                'dst_node_id' => $targetId,
                'port_id_a' => $linkData['port_id_a'] ?? null,
                'port_id_b' => $linkData['port_id_b'] ?? null,
                'bandwidth_bps' => $linkData['bandwidth_bps'] ?? null,
                'style' => $linkData['style'] ?? [],
            ]);
        }
    }

    private function resolveNodeId(array $linkData, array $nodeIdMap, string $type): ?int
    {
        $key = $type === 'src' ? 'source' : 'target';
        $altKey = $type === 'src' ? 'src_node_id' : 'dst_node_id';

        $oldId = $linkData[$type] ?? $linkData[$key] ?? $linkData[$altKey] ?? null;
        return $nodeIdMap[$oldId] ?? (is_numeric($oldId) ? (int)$oldId : null);
    }
}
