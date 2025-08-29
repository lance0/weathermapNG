<?php
namespace LibreNMS\Plugins\WeathermapNG\Models;

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

    public function getSourcePortNameAttribute()
    {
        if (!$this->port_id_a) {
            return null;
        }

        try {
            if (class_exists('\App\Models\Port')) {
                $port = \App\Models\Port::find($this->port_id_a);
                return $port ? $port->ifName : null;
            }

            // Fallback
            $port = dbFetchRow("SELECT ifName FROM ports WHERE port_id = ?", [$this->port_id_a]);
            return $port ? $port['ifName'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getDestinationPortNameAttribute()
    {
        if (!$this->port_id_b) {
            return null;
        }

        try {
            if (class_exists('\App\Models\Port')) {
                $port = \App\Models\Port::find($this->port_id_b);
                return $port ? $port->ifName : null;
            }

            // Fallback
            $port = dbFetchRow("SELECT ifName FROM ports WHERE port_id = ?", [$this->port_id_b]);
            return $port ? $port['ifName'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getBandwidthFormattedAttribute()
    {
        if (!$this->bandwidth_bps) {
            return 'Unknown';
        }

        $bps = $this->bandwidth_bps;

        if ($bps >= 1000000000) {
            return round($bps / 1000000000, 1) . ' Gbps';
        } elseif ($bps >= 1000000) {
            return round($bps / 1000000, 1) . ' Mbps';
        } elseif ($bps >= 1000) {
            return round($bps / 1000, 1) . ' Kbps';
        } else {
            return $bps . ' bps';
        }
    }

    public function toArray()
    {
        $data = parent::toArray();
        $data['source_port_name'] = $this->source_port_name;
        $data['destination_port_name'] = $this->destination_port_name;
        $data['bandwidth_formatted'] = $this->bandwidth_formatted;
        return $data;
    }
}