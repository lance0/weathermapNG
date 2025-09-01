<?php

namespace App\Plugins\WeathermapNG\Models;

use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    protected $table = 'wmng_maps';
    protected $fillable = ['name', 'title', 'description', 'width', 'height', 'options'];
    protected $casts = ['options' => 'array'];

    public function nodes()
    {
        return $this->hasMany(Node::class);
    }

    public function links()
    {
        return $this->hasMany(Link::class);
    }

    public function toJsonModel()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'width' => $this->width,
            'height' => $this->height,
            'options' => $this->options ?? new \stdClass(),
            'nodes' => $this->nodes()->get(['id', 'label', 'x', 'y', 'device_id', 'meta']),
            'links' => $this->links()->get([
                'id',
                'src_node_id as src',
                'dst_node_id as dst',
                'port_id_a',
                'port_id_b',
                'bandwidth_bps',
                'style'
            ]),
        ];
    }
}