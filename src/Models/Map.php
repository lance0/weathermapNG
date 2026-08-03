<?php

namespace LibreNMS\Plugins\WeathermapNG\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Map extends Model
{
    protected $table = 'wmng_maps';
    protected $fillable = ['name', 'title', 'options'];
    protected $casts = ['options' => 'array'];

    public function nodes()
    {
        return $this->hasMany(Node::class);
    }

    public function links()
    {
        return $this->hasMany(Link::class);
    }

    public function getWidthAttribute()
    {
        return data_get($this->options, 'width', 800);
    }

    public function getHeightAttribute()
    {
        return data_get($this->options, 'height', 600);
    }

    public function getBackgroundAttribute()
    {
        return data_get($this->options, 'background', '#ffffff');
    }

    public function getTagsAttribute()
    {
        $tags = data_get($this->options, 'tags', []);
        if (!is_array($tags)) {
            return [];
        }
        $normalized = array_map(fn($t) => is_string($t) ? strtolower(trim($t)) : '', $tags);
        return array_values(array_unique(array_filter($normalized, fn($t) => $t !== '')));
    }

    public function getDefaultNodeStyleAttribute(): array
    {
        $style = data_get($this->options, 'default_node_style', []);
        return is_array($style) ? $style : [];
    }

    public function getDefaultLinkStyleAttribute(): array
    {
        $style = data_get($this->options, 'default_link_style', []);
        return is_array($style) ? $style : [];
    }

    public function toJsonModel()
    {
        // Prime batch caches so accessor-backed fields (device_name, status,
        // source_port_name, destination_port_name) don't fire N+1 queries.
        $deviceIds = $this->nodes->pluck('device_id')->filter()->unique()->values()->all();
        Node::preloadDevices($deviceIds);

        $portIds = $this->links->flatMap(fn($l) => [$l->port_id_a, $l->port_id_b])
            ->filter(fn($id) => $id !== null && $id !== 0)
            ->unique()
            ->values()
            ->all();
        Link::preloadPortNames($portIds);

        return [
            '_format' => 'weathermapng-map-v1',
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'width' => $this->width,
            'height' => $this->height,
            'background' => $this->background,
            'options' => $this->options ?? new \stdClass(),
            'nodes' => $this->nodes->map(fn($n) => [
                'id' => $n->id,
                'label' => $n->label,
                'x' => $n->x,
                'y' => $n->y,
                'device_id' => $n->device_id,
                'meta' => $n->meta,
                'device_name' => $n->device_name,
                'status' => $n->status,
            ])->toArray(),
            'links' => $this->links->map(fn($l) => [
                'id' => $l->id,
                'src' => $l->src_node_id,
                'dst' => $l->dst_node_id,
                'port_id_a' => $l->port_id_a,
                'port_id_b' => $l->port_id_b,
                'bandwidth_bps' => $l->bandwidth_bps,
                'style' => $l->style,
                'source_port_name' => $l->source_port_name,
                'destination_port_name' => $l->destination_port_name,
                'bandwidth_formatted' => $l->bandwidth_formatted,
            ])->toArray(),
        ];
    }

    /**
     * Create a new Map (with nodes and links) from decoded JSON export data.
     *
     * The caller supplies the `name` and `title` because the `name` column has
     * a unique constraint — the user must choose one rather than inheriting it
     * from the file. Width/height/background are read from either the
     * top-level keys (server export shape) or nested under `options`
     * (client export shape) and merged into the stored options.
     *
     * Returns the persisted Map model. Runs in a DB transaction so a bad
     * node/link reference rolls back the whole map.
     */
    public static function createFromJsonData(array $data, string $name, ?string $title = null): Map
    {
        return DB::transaction(function () use ($data, $name, $title) {
            $options = is_array($data['options'] ?? null) ? $data['options'] : [];

            // Merge top-level width/height/background (server export shape) into
            // options so the stored options are self-consistent regardless of
            // whether the file came from toJsonModel() or the editor's exportJson().
            foreach (['width', 'height', 'background'] as $key) {
                if (array_key_exists($key, $data) && !array_key_exists($key, $options)) {
                    $options[$key] = $data[$key];
                }
            }
            $options['width'] = $options['width'] ?? 800;
            $options['height'] = $options['height'] ?? 600;
            $options['background'] = $options['background'] ?? '#ffffff';

            $map = Map::create([
                'name' => $name,
                'title' => $title ?? $name,
                'options' => $options,
            ]);

            $nodeIdMap = self::createNodesFromData($map, $data['nodes'] ?? []);
            self::createLinksFromData($map, $data['links'] ?? [], $nodeIdMap);

            return $map;
        });
    }

    private static function createNodesFromData(Map $map, array $nodesData): array
    {
        $nodeIdMap = [];

        foreach ($nodesData as $index => $nodeData) {
            $node = Node::create([
                'map_id' => $map->id,
                'label' => $nodeData['label'] ?? 'Node',
                'x' => isset($nodeData['x']) && is_numeric($nodeData['x']) ? (float) $nodeData['x'] : 0,
                'y' => isset($nodeData['y']) && is_numeric($nodeData['y']) ? (float) $nodeData['y'] : 0,
                'device_id' => isset($nodeData['device_id']) && is_numeric($nodeData['device_id']) ? (int) $nodeData['device_id'] : null,
                'meta' => is_array($nodeData['meta'] ?? null) ? $nodeData['meta'] : [],
            ]);

            $clientKey = $nodeData['id'] ?? $nodeData['node_id'] ?? $nodeData['_id'] ?? (string) $index;
            $nodeIdMap[$clientKey] = $node->id;
        }

        return $nodeIdMap;
    }

    private static function createLinksFromData(Map $map, array $linksData, array $nodeIdMap): void
    {
        foreach ($linksData as $linkData) {
            $sourceId = self::resolveNodeIdFromMap($linkData['src_node_id'] ?? $linkData['source'] ?? $linkData['src'] ?? null, $nodeIdMap);
            $targetId = self::resolveNodeIdFromMap($linkData['dst_node_id'] ?? $linkData['target'] ?? $linkData['dst'] ?? null, $nodeIdMap);
            if (!$sourceId || !$targetId) {
                Log::warning('WeathermapNG: dropped link with unresolvable node reference', [
                    'map_id' => $map->id,
                    'src' => $linkData['src_node_id'] ?? $linkData['source'] ?? $linkData['src'] ?? null,
                    'dst' => $linkData['dst_node_id'] ?? $linkData['target'] ?? $linkData['dst'] ?? null,
                ]);
                continue;
            }

            Link::create([
                'map_id' => $map->id,
                'src_node_id' => $sourceId,
                'dst_node_id' => $targetId,
                'port_id_a' => isset($linkData['port_id_a']) && is_numeric($linkData['port_id_a']) ? (int) $linkData['port_id_a'] : (isset($linkData['port_a']) && is_numeric($linkData['port_a']) ? (int) $linkData['port_a'] : null),
                'port_id_b' => isset($linkData['port_id_b']) && is_numeric($linkData['port_id_b']) ? (int) $linkData['port_id_b'] : (isset($linkData['port_b']) && is_numeric($linkData['port_b']) ? (int) $linkData['port_b'] : null),
                'bandwidth_bps' => isset($linkData['bandwidth_bps']) && is_numeric($linkData['bandwidth_bps']) ? (int) $linkData['bandwidth_bps'] : (isset($linkData['bandwidth']) && is_numeric($linkData['bandwidth']) ? (int) $linkData['bandwidth'] : null),
                'style' => $linkData['style'] ?? [],
            ]);
        }
    }

    private static function resolveNodeIdFromMap($clientId, array $nodeIdMap): ?int
    {
        if ($clientId === null) {
            return null;
        }

        return $nodeIdMap[$clientId] ?? null;
    }
}
