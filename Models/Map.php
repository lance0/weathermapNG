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

    public function toJsonModel()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'width' => $this->width,
            'height' => $this->height,
            'background' => $this->background,
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