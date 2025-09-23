<?php

namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    protected $table = 'wmng_nodes';
    protected $fillable = ['map_id', 'label', 'x', 'y', 'device_id', 'meta'];

    public function map()
    {
        return $this->belongsTo(Map::class);
    }

    public function links()
    {
        return $this->hasMany(Link::class, 'source_node_id')
                    ->orWhere('target_node_id', $this->id);
    }
}