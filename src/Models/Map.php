<?php

namespace LibreNMS\Plugins\WeathermapNG\Models;

use Illuminate\Database\Eloquent\Model;

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
}
