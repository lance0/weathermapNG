<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\Models\MapVersion;

class MapVersionService
{
    public function __construct(private readonly WebhookDispatcher $webhooks)
    {
    }

    public function createVersion(Map $map, string $name, ?string $description = null, ?int $userId = null): MapVersion
    {
        return DB::transaction(function () use ($map, $name, $description, $userId) {
            $fresh = Map::whereKey($map->id)->lockForUpdate()->firstOrFail();
            return $this->insertVersion($fresh, $name, $description, $userId);
        });
    }

    private function insertVersion(Map $map, string $name, ?string $description = null, ?int $userId = null): MapVersion
    {
        $version = MapVersion::create([
            'map_id' => $map->id,
            'name' => $name,
            'description' => $description,
            'config_snapshot' => $this->captureSnapshot($map),
            'created_by' => $userId,
        ]);

        $payload = ['map_id' => (int) $map->id, 'map_name' => (string) $map->name];
        $version->getConnection()->afterCommit(function () use ($payload) {
            try {
                $this->webhooks->dispatch('map.version_saved', $payload);
            } catch (\Throwable $e) {
                // Delivery failure must not make a committed restore look rolled back.
                Log::warning('WeathermapNG: version webhook delivery failed', ['map_id' => $payload['map_id']]);
            }
        });

        return $version;
    }

    public function restoreVersion(MapVersion $version, ?int $userId = null): Map
    {
        return DB::transaction(function () use ($version, $userId) {
            // Serializes cooperating restores; editor saves do not yet take this lock.
            $map = Map::whereKey($version->map_id)->lockForUpdate()->firstOrFail();
            $target = MapVersion::where('map_id', $map->id)->findOrFail($version->id);
            $snapshot = $target->config_snapshot;
            $this->validateSnapshot($snapshot, $map);

            $this->insertVersion($map, "Before restore #{$target->id}", null, $userId);
            $map->links()->delete();
            $map->nodes()->delete();

            $allowedNode = array_flip(['id', 'label', 'x', 'y', 'device_id', 'meta']);
            foreach ($snapshot['nodes'] as $nodeData) {
                Node::forceCreate(array_merge(array_intersect_key($nodeData, $allowedNode), ['map_id' => $map->id]));
            }
            $allowedLink = array_flip(['id', 'src_node_id', 'dst_node_id', 'port_id_a', 'port_id_b', 'bandwidth_bps', 'style']);
            foreach ($snapshot['links'] as $linkData) {
                Link::forceCreate(array_merge(array_intersect_key($linkData, $allowedLink), ['map_id' => $map->id]));
            }

            $data = $snapshot['map'];
            $map->fill(array_intersect_key($data, array_flip(['name', 'title', 'description', 'parent_map_id'])));
            if (array_key_exists('options', $data)) {
                $map->options = $data['options'];
            } else {
                // Older snapshots only captured these three options.
                $map->options = array_replace($map->options ?? [], array_intersect_key($data, array_flip(['width', 'height', 'background'])));
            }
            foreach (['width', 'height'] as $field) {
                if (array_key_exists('raw_' . $field, $data)) {
                    $map->setAttribute($field, $data['raw_' . $field]);
                }
            }
            $map->save();
            $map = $map->fresh();
            $this->insertVersion($map, "Restored version #{$target->id}", null, $userId);

            return $map;
        });
    }

    private function validateSnapshot(mixed $snapshot, Map $map): void
    {
        $invalid = static function (): never {
            throw new \InvalidArgumentException('Version snapshot cannot be safely restored.');
        };
        if (!is_array($snapshot) || !is_array($snapshot['map'] ?? null)
            || !is_array($snapshot['nodes'] ?? null) || !is_array($snapshot['links'] ?? null)) {
            $invalid();
        }
        $data = $snapshot['map'];
        if (!$this->isInteger($data['id'] ?? null, 1) || (int) $data['id'] !== (int) $map->id) {
            $invalid();
        }
        foreach (['name', 'title', 'description', 'background'] as $field) {
            if (array_key_exists($field, $data) && !is_string($data[$field])
                && !($field !== 'name' && $data[$field] === null)) {
                $invalid();
            }
        }
        foreach (['width', 'height'] as $field) {
            if (isset($data[$field]) && (!is_numeric($data[$field]) || !is_finite((float) $data[$field]))) {
                $invalid();
            }
        }
        foreach (['raw_width', 'raw_height'] as $field) {
            if (array_key_exists($field, $data) && !$this->isInteger($data[$field], 0)) {
                $invalid();
            }
        }
        if (isset($data['options']) && !is_array($data['options'])) {
            $invalid();
        }
        if (isset($data['parent_map_id'])) {
            $this->validateMapReference($data['parent_map_id']);
            $seen = [(int) $map->id => true];
            $parent = $data['parent_map_id'];
            while ($parent !== null) {
                if (isset($seen[(int) $parent])) {
                    $invalid();
                }
                $seen[(int) $parent] = true;
                $parent = Map::find($parent)?->parent_map_id;
            }
        }

        $nodeIds = [];
        foreach (['nodes' => Node::class, 'links' => Link::class] as $type => $model) {
            $ids = [];
            foreach ($snapshot[$type] as $record) {
                if (!is_array($record) || !$this->isInteger($record['id'] ?? null, 1)
                    || isset($ids[(int) $record['id']])) {
                    $invalid();
                }
                $ids[(int) $record['id']] = true;
                foreach ($type === 'nodes' ? ['device_id'] : ['port_id_a', 'port_id_b', 'bandwidth_bps'] as $field) {
                    if (isset($record[$field]) && !$this->isInteger($record[$field], 0)) {
                        $invalid();
                    }
                }
                $jsonField = $type === 'nodes' ? 'meta' : 'style';
                if (isset($record[$jsonField]) && !is_array($record[$jsonField])) {
                    $invalid();
                }
                if ($type === 'nodes') {
                    if (!is_string($record['label'] ?? null)) {
                        $invalid();
                    }
                    foreach (['x', 'y'] as $field) {
                        if (!is_numeric($record[$field] ?? null) || !is_finite((float) $record[$field])) {
                            $invalid();
                        }
                    }
                    if (isset($record['meta']['sub_map_id'])) {
                        $this->validateMapReference($record['meta']['sub_map_id']);
                    }
                } else {
                    foreach (['src_node_id', 'dst_node_id'] as $field) {
                        if (!$this->isInteger($record[$field] ?? null, 1) || !isset($nodeIds[(int) $record[$field]])) {
                            $invalid();
                        }
                    }
                }
            }
            // IDs are global primary keys, not scoped to a map.
            if ($model::whereIn('id', array_keys($ids))->where('map_id', '!=', $map->id)->exists()) {
                $invalid();
            }
            if ($type === 'nodes') {
                $nodeIds = $ids;
            }
        }
    }

    private function isInteger(mixed $value, int $minimum): bool
    {
        return (is_int($value) || is_string($value))
            && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]) !== false;
    }

    private function validateMapReference(mixed $id): void
    {
        if (!$this->isInteger($id, 1) || !Map::whereKey($id)->exists()) {
            throw new \InvalidArgumentException('Version snapshot refers to an unavailable map.');
        }
    }

    public function deleteVersion(MapVersion $version): void
    {
        $version->delete();
    }

    public function getVersions(Map $map, int $limit = 10): \Illuminate\Support\Collection
    {
        return MapVersion::with('creator')->versions($map->id, $limit)->get();
    }

    public function getVersion(Map $map, int $versionId): ?MapVersion
    {
        return MapVersion::where('map_id', $map->id)->find($versionId);
    }

    public function getLatestVersion(Map $map): ?MapVersion
    {
        return MapVersion::latestForMap($map->id)->first();
    }

    public function compareVersions(MapVersion $version1, MapVersion $version2): array
    {
        [$nodesAdded, $nodesRemoved] = $this->getAddedRemovedIds($version1, $version2, 'nodes');
        [$linksAdded, $linksRemoved] = $this->getAddedRemovedIds($version1, $version2, 'links');

        return [
            'nodes_added' => $nodesAdded,
            'nodes_removed' => $nodesRemoved,
            'nodes_modified' => $this->getModifiedIds($version1, $version2, 'nodes'),
            'links_added' => $linksAdded,
            'links_removed' => $linksRemoved,
            'links_modified' => $this->getModifiedIds($version1, $version2, 'links'),
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
                'description' => $map->description,
                'parent_map_id' => $map->parent_map_id,
                'options' => $map->options,
                'raw_width' => $map->getRawOriginal('width'),
                'raw_height' => $map->getRawOriginal('height'),
            ],
            'nodes' => $map->nodes()->get()->map(fn($node) => [
                'id' => $node->id,
                'label' => $node->label,
                'x' => $node->x,
                'y' => $node->y,
                'device_id' => $node->device_id,
                'meta' => $node->meta,
            ])->keyBy('id')->all(),
            'links' => $map->links()->get()->map(fn($link) => [
                'id' => $link->id,
                'src_node_id' => $link->src_node_id,
                'dst_node_id' => $link->dst_node_id,
                'port_id_a' => $link->port_id_a,
                'port_id_b' => $link->port_id_b,
                'bandwidth_bps' => $link->bandwidth_bps,
                'style' => $link->style,
            ])->keyBy('id')->all(),
        ];
    }

    private function getSnapshotNodes(MapVersion $version): \Illuminate\Support\Collection
    {
        $snapshot = $version->config_snapshot;
        if (!is_array($snapshot)) {
            $snapshot = json_decode($snapshot ?? '', true) ?: [];
        }
        return collect($snapshot['nodes'] ?? []);
    }

    private function getSnapshotLinks(MapVersion $version): \Illuminate\Support\Collection
    {
        $snapshot = $version->config_snapshot;
        if (!is_array($snapshot)) {
            $snapshot = json_decode($snapshot ?? '', true) ?: [];
        }
        return collect($snapshot['links'] ?? []);
    }

    private function getAddedRemovedIds(MapVersion $v1, MapVersion $v2, string $type): array
    {
        $items1 = $type === 'nodes' ? $this->getSnapshotNodes($v1) : $this->getSnapshotLinks($v1);
        $items2 = $type === 'nodes' ? $this->getSnapshotNodes($v2) : $this->getSnapshotLinks($v2);
        $ids1 = $items1->keys()->all();
        $ids2 = $items2->keys()->all();
        // Direction: v1 → v2 transition. Added = in v2 but not v1.
        // Removed = in v1 but not v2.
        return [
            array_values(array_diff($ids2, $ids1)),
            array_values(array_diff($ids1, $ids2)),
        ];
    }

    private function getModifiedIds(MapVersion $v1, MapVersion $v2, string $type): array
    {
        $items1 = $type === 'nodes' ? $this->getSnapshotNodes($v1) : $this->getSnapshotLinks($v1);
        $items2 = $type === 'nodes' ? $this->getSnapshotNodes($v2) : $this->getSnapshotLinks($v2);

        $modified = [];
        foreach ($items1 as $id => $item) {
            $item2 = $items2->get($id);
            if ($item2 && $item != $item2) {
                $modified[] = $id;
            }
        }

        return $modified;
    }
}
