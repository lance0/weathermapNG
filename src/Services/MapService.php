<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MapService
{
    public function createMap(array $data): Map
    {
        $options = [
            'width' => $data['width'] ?? 800,
            'height' => $data['height'] ?? 600,
            'background' => '#ffffff',
        ];

        return Map::create([
            'name' => $data['name'],
            'title' => $data['title'] ?? $data['name'],
            'options' => $options,
        ]);
    }

    public function updateMap(Map $map, array $data): Map
    {
        $options = $map->options ?? [];
        $options['width'] = $data['width'] ?? $options['width'] ?? 800;
        $options['height'] = $data['height'] ?? $options['height'] ?? 600;
        $options['background'] = $data['background'] ?? $options['background'] ?? '#ffffff';

        $update = ['options' => $options];
        if (array_key_exists('title', $data) && Schema::hasColumn('wmng_maps', 'title')) {
            $update['title'] = $data['title'] ?? $map->title;
        }

        $map->update($update);
        return $map->refresh();
    }

    public function deleteMap(Map $map): void
    {
        $map->delete();
    }

    public function saveMap(Map $map, array $data): void
    {
        DB::transaction(function () use ($map, $data) {
            $this->updateMapProperties($map, $data);
            $this->replaceMapContent($map, $data);
        });
    }

    private function updateMapProperties(Map $map, array $data): void
    {
        if (empty($data['options']) && !array_key_exists('title', $data)) {
            return;
        }

        $updates = [];

        if (!empty($data['options'])) {
            $updates['options'] = $this->mergeMapOptions($map->options ?? [], $data['options']);
        }

        if (array_key_exists('title', $data) && Schema::hasColumn('wmng_maps', 'title')) {
            $updates['title'] = $data['title'];
        }

        if (!empty($updates)) {
            $map->fill($updates)->save();
        }
    }

    private function mergeMapOptions(array $currentOptions, array $newOptions): array
    {
        return array_merge($currentOptions, array_filter([
            'width' => $newOptions['width'] ?? $currentOptions['width'] ?? 800,
            'height' => $newOptions['height'] ?? $currentOptions['height'] ?? 600,
            'background' => $newOptions['background'] ?? $currentOptions['background'] ?? null,
        ], fn($value) => $value !== null));
    }

    private function replaceMapContent(Map $map, array $data): void
    {
        $map->links()->delete();
        $map->nodes()->delete();

        $nodeIdMap = $this->createNodes($map, $data['nodes'] ?? []);
        $this->createLinks($map, $data['links'] ?? [], $nodeIdMap);
    }

    private function createNodes(Map $map, array $nodesData): array
    {
        $nodeIdMap = [];

        foreach ($nodesData as $index => $nodeData) {
            $node = Node::create([
                'map_id' => $map->id,
                'label' => $nodeData['label'],
                'x' => $nodeData['x'],
                'y' => $nodeData['y'],
                'device_id' => $nodeData['device_id'] ?? null,
                'meta' => $nodeData['meta'] ?? [],
            ]);

            $clientKey = $nodeData['id'] ?? $nodeData['node_id'] ?? $nodeData['_id'] ?? (string)$index;
            $nodeIdMap[$clientKey] = $node->id;
        }

        return $nodeIdMap;
    }

    private function createLinks(Map $map, array $linksData, array $nodeIdMap): void
    {
        foreach ($linksData as $linkData) {
            $sourceId = $this->resolveNodeId($linkData['src_node_id'] ?? $linkData['source'] ?? null, $nodeIdMap);
            $targetId = $this->resolveNodeId($linkData['dst_node_id'] ?? $linkData['target'] ?? null, $nodeIdMap);

            if (!$sourceId || !$targetId) {
                continue;
            }

            Link::create([
                'map_id' => $map->id,
                'src_node_id' => $sourceId,
                'dst_node_id' => $targetId,
                'port_id_a' => $linkData['port_id_a'] ?? $linkData['port_a'] ?? null,
                'port_id_b' => $linkData['port_id_b'] ?? $linkData['port_b'] ?? null,
                'bandwidth_bps' => $linkData['bandwidth_bps'] ?? $linkData['bandwidth'] ?? null,
                'style' => $linkData['style'] ?? [],
            ]);
        }
    }

    private function resolveNodeId(?string $clientId, array $nodeIdMap): ?int
    {
        if ($clientId === null) {
            return null;
        }

        return $nodeIdMap[$clientId] ?? (int)$clientId;
    }

    public function importMap(Request $request, array $validated): Map
    {
        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true);

        if (!$data || !isset($data['nodes']) || !isset($data['links'])) {
            throw new \InvalidArgumentException('Invalid map file format');
        }

        $map = $this->createMap([
            'name' => $validated['name'],
            'title' => $validated['title'] ?? $validated['name'],
            'width' => $data['options']['width'] ?? null,
            'height' => $data['options']['height'] ?? null,
        ]);

        $nodeIdMap = $this->createNodes($map, $data['nodes']);
        $this->createLinks($map, $data['links'], $nodeIdMap);

        return $map;
    }
}
