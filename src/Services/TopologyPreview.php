<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\DB;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\MapFavorite;

class TopologyPreview
{
    /**
     * Load the index page's maps, their topology previews, and per-user
     * favorite state. Favorite counts and the current user's favorites are
     * each resolved with one query for all maps on the page (never per card).
     */
    public static function loadMaps(?int $userId = null): array
    {
        $maps = Map::withCount(['nodes', 'links'])->with([
            'nodes' => fn($relation) => $relation->select(['id','map_id','x','y'])->orderBy('id')->limit(100),
            'links' => fn($relation) => $relation->select(['id','map_id','src_node_id','dst_node_id'])->orderBy('id')->limit(150),
        ])->orderBy('name')->get();

        self::attachFavorites($maps, $userId);

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

    private static function attachFavorites($maps, ?int $userId): void
    {
        $mapIds = $maps->pluck('id')->map(fn($id) => (int) $id)->all();

        // Installs upgraded without re-running database/setup.php still render
        // the index; favorites stay empty until the table exists. Resolve the
        // schema through the model's connection so this works with whatever
        // connection the caller booted (including the isolated test Capsule).
        $schema = (new MapFavorite())->getConnection()->getSchemaBuilder();
        if ($mapIds === [] || !$schema->hasTable('wmng_map_favorites')) {
            foreach ($maps as $map) {
                $map->setAttribute('favorite_count', 0);
                $map->setAttribute('favorited', false);
            }
            return;
        }

        // One grouped count for every card on the page.
        $counts = MapFavorite::whereIn('map_id', $mapIds)
            ->select('map_id', DB::raw('COUNT(*) as favorite_count'))
            ->groupBy('map_id')
            ->pluck('favorite_count', 'map_id')
            ->all();

        // At most one more query, only when a user is signed in.
        $favorited = [];
        if ($userId !== null) {
            $favorited = array_flip(array_map('intval',
                MapFavorite::where('user_id', $userId)->whereIn('map_id', $mapIds)->pluck('map_id')->all()
            ));
        }

        foreach ($maps as $map) {
            $map->setAttribute('favorite_count', (int) ($counts[$map->id] ?? 0));
            $map->setAttribute('favorited', isset($favorited[(int) $map->id]));
        }
    }

    private static function coordinate(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float)$value) ? (float)$value : null;
    }
}
