<?php

namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Database\Eloquent\Model;

class Link extends Model
{
    protected $table = 'wmng_links';
    protected $fillable = ['map_id', 'source_node_id', 'target_node_id', 'source_port_id', 'target_port_id', 'meta'];

    public function map()
    {
        return $this->belongsTo(Map::class);
    }

    public function sourceNode()
    {
        return $this->belongsTo(Node::class, 'source_node_id');
    }

    public function targetNode()
    {
        return $this->belongsTo(Node::class, 'target_node_id');
    }
}