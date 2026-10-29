<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;

class TopologyPreview
{
    public static function loadMaps(): array
    {
        $maps = Map::withCount(['nodes', 'links'])->with([
            'nodes' => fn($relation) => $relation->select(['id','map_id','x','y'])->orderBy('id')->limit(100),
            'links' => fn($relation) => $relation->select(['id','map_id','src_node_id','dst_node_id'])->orderBy('id')->limit(150),
        ])->orderBy('name')->get();
        $previews = [];
        foreach ($maps as $map) {
            // Explicit attributes avoid Node/Link serialization and its device/port appends.
            $previews[$map->id] = [
                'nodes' => $map->nodes->map(fn($node) => [
                    'id' => (int)$node->getRawOriginal('id'),
                    'x' => self::coordinate($node->getRawOriginal('x')),
                    'y' => self::coordinate($node->getRawOriginal('y')),
                ])->all(),
                'links' => $map->links->map(fn($link) => [
                    'src' => (int)$link->getRawOriginal('src_node_id'),
                    'dst' => (int)$link->getRawOriginal('dst_node_id'),
                ])->all(),
                'capped' => $map->nodes_count > 100 || $map->links_count > 150,
            ];
        }
        return ['maps' => $maps, 'previews' => $previews];
    }

    private static function coordinate(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float)$value) ? (float)$value : null;
    }
}
