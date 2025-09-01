<?php

namespace App\Plugins\WeathermapNG\Models;

use Illuminate\Database\Eloquent\Model;

class Link extends Model
{
    protected $table = 'wmng_links';
    protected $fillable = [
        'map_id',
        'src_node_id',
        'dst_node_id',
        'port_id_a',
        'port_id_b',
        'bandwidth_bps',
        'style'
    ];
    protected $casts = ['style' => 'array'];

    public function map()
    {
        return $this->belongsTo(Map::class);
    }

    public function sourceNode()
    {
        return $this->belongsTo(Node::class, 'src_node_id');
    }

    public function destinationNode()
    {
        return $this->belongsTo(Node::class, 'dst_node_id');
    }

    public function getSourceDeviceNameAttribute()
    {
        return $this->sourceNode ? $this->sourceNode->device_name : null;
    }

    public function getDestinationDeviceNameAttribute()
    {
        return $this->destinationNode ? $this->destinationNode->device_name : null;
    }

    public function getTrafficDataAttribute()
    {
        // This would be implemented to fetch real traffic data
        // For now, return placeholder data
        return [
            'in' => 0,
            'out' => 0,
            'percentage' => 0,
        ];
    }
}