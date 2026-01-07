<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\DB;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\Models\MapVersion;

class MapVersionService
{
    public function createVersion(Map $map, string $name, ?string $description = null, bool $autoSave = false, ?int $userId = null): MapVersion
    {
        return MapVersion::create([
            'map_id' => $map->id,
            'name' => $name,
            'description' => $description,
            'config_snapshot' => $this->captureSnapshot($map),
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    public function restoreVersion(MapVersion $version): Map
    {
        $snapshot = json_decode($version->config_snapshot, true);

        if (isset($snapshot['nodes'])) {
            foreach ($snapshot['nodes'] as $nodeData) {
                if (isset($nodeData['id'])) {
                    Node::where('id', $nodeData['id'])->update($nodeData);
                } else {
                    Node::create($nodeData);
                }
            }
        }

        if (isset($snapshot['links'])) {
            foreach ($snapshot['links'] as $linkData) {
                if (isset($linkData['id'])) {
                    Link::where('id', $linkData['id'])->update($linkData);
                } else {
                    Link::create($linkData);
                }
            }
        }

        $map->update([
            'title' => $snapshot['map']['title'] ?? null,
            'width' => $snapshot['map']['width'] ?? null,
            'height' => $snapshot['map']['height'] ?? null,
            'background' => $snapshot['map']['background'] ?? null,
        ]);

        return $map->fresh();
    }

    public function deleteVersionsOlderThan(MapVersion $version): void
    {
        DB::transaction(function () use ($version) {
            $this->deleteVersionsOlderThanInternal($version->map_id, $version->id);
        });
    }

    public function getVersions(Map $map, int $limit = 10): \Illuminate\Support\Collection
    {
        return MapVersion::versions($map->id, $limit);
    }

    public function getVersion(Map $map, int $versionId): ?MapVersion
    {
        return MapVersion::find($versionId);
    }

    public function getLatestVersion(Map $map): ?MapVersion
    {
        return MapVersion::latestForMap($map->id)->first();
    }

    public function compareVersions(MapVersion $version1, MapVersion $version2): array
    {
        return [
            'nodes_added' => $this->compareNodes($version1, $version2),
            'nodes_removed' => $this->compareNodes($version2, $version1),
            'nodes_modified' => $this->compareNodesModified($version1, $version2),
            'links_added' => $this->compareLinks($version1, $version2),
            'links_removed' => $this->compareLinks($version2, $version1),
            'links_modified' => $this->compareLinksModified($version1, $version2),
        ];
    }

    private function captureSnapshot(Map $map): array
    {
        return [
            'map' => [
                'id' => $map->id,
                'name' => $map->name,
                'title' => $map->title,
                'width' => $map->width,
                'height' => $map->height,
                'background' => $map->background,
            ],
            'nodes' => $map->nodes->map(fn($node) => [
                'id' => $node->id,
                'label' => $node->label,
                'x' => $node->x,
                'y' => $node->y,
                'device_id' => $node->database_id,
                'meta' => $node->meta,
            ])->keyBy('id'),
            'links' => $map->links->map(fn($link) => [
                'id' => $link->id,
                'src_node_id' => $link->src_node_id,
                'dst_node_id' => $link->dst_node_id,
                'port_id_a' => $link->port_id_a,
                'port_id_b' => $link->port_id_b,
                'bandwidth_bps' => $link->bandwidth_bps,
                'style' => $link->style,
                'meta' => $link->meta,
            ])->keyBy('id'),
        ];
    }

    private function compareNodes(MapVersion $version1, MapVersion $version2): array
    {
        $nodes1 = collect(json_decode($version1->config_snapshot, true)['nodes']);
        $nodes2 = collect(json_decode($version2->config_snapshot, true)['nodes']);

        return [
            'added' => $nodes1->diff($nodes2)->keys()->values()->all(),
            'removed' => $nodes2->diff($nodes1)->keys()->values()->all(),
        ];
    }

    private function compareNodesModified(MapVersion $version1, MapVersion $version2): array
    {
        $nodes1 = collect(json_decode($version1->config_snapshot, true)['nodes']);
        $nodes2 = collect(json_decode($version2->config_snapshot, true)['nodes']);

        $modified = [];
        foreach ($nodes1 as $id => $node) {
            $node2 = $nodes2->get($id);
            if ($node2 && $node != $node2) {
                $modified[] = $id;
            }
        }

        return $modified;
    }

    private function compareLinks(MapVersion $version1, MapVersion $version2): array
    {
        $links1 = collect(json_decode($version1->config_snapshot, true)['links']);
        $links2 = collect(json_decode($version2->config_snapshot, true)['links']);

        return [
            'added' => $links1->diff($links2)->keys()->values()->all(),
            'removed' => $links2->diff($links1)->keys()->values()->all(),
        ];
    }

    private function compareLinksModified(MapVersion $version1, MapVersion $version2): array
    {
        $links1 = collect(json_decode($version1->config_snapshot, true)['links']);
        $links2 = collect(json_decode($version2->config_snapshot, true)['links']);

        $modified = [];
        foreach ($links1 as $id => $link) {
            $link2 = $links2->get($id);
            if ($link2 && $link != $link2) {
                $modified[] = $id;
            }
        }

        return $modified;
    }

    private function deleteVersionsOlderThanInternal(int $mapId, int $versionId): void
    {
        MapVersion::where('map_id', $mapId)
            ->where('id', '>', $versionId)
            ->delete();
    }
}
