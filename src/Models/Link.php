<?php

namespace LibreNMS\Plugins\WeathermapNG\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    /** @var array<int,?string> Batch-prefetch cache for port ifName lookups. */
    private static array $portNameCache = [];

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
        return $this->resolvePortName($this->port_id_a ?: null);
    }

    public function getDestinationPortNameAttribute()
    {
        return $this->resolvePortName($this->port_id_b ?: null);
    }

    /**
     * Resolve a port's ifName from the static cache, falling back to a single
     * lookup that primes the cache so subsequent accesses for the same port_id
     * do not re-query.
     */
    private function resolvePortName(?int $portId): ?string
    {
        if ($portId === null || $portId === 0) {
            return null;
        }

        if (array_key_exists($portId, self::$portNameCache)) {
            return self::$portNameCache[$portId];
        }

        try {
            if (class_exists('\App\Models\Port')) {
                $port = \App\Models\Port::find($portId);
                $ifName = $port ? $port->ifName : null;
            } else {
                // Fallback
                $port = dbFetchRow("SELECT ifName FROM ports WHERE port_id = ?", [$portId]);
                $ifName = $port ? $port['ifName'] : null;
            }
        } catch (\Exception $e) {
            $ifName = null;
        }

        return self::$portNameCache[$portId] = $ifName;
    }

    /**
     * Prime the port-name cache for a set of port IDs in a single query so
     * serializing a collection of links does not issue one query per port.
     *
     * @param  array  $portIds
     * @return void
     */
    public static function preloadPortNames(array $portIds): void
    {
        $portIds = array_values(array_filter(array_map('intval', $portIds), fn ($id) => $id !== 0));
        if (empty($portIds)) {
            return;
        }

        try {
            if (class_exists('\App\Models\Port')) {
                $mapping = \App\Models\Port::whereIn('port_id', $portIds)->pluck('ifName', 'port_id');
            } else {
                $mapping = DB::table('ports')->whereIn('port_id', $portIds)->pluck('ifName', 'port_id');
            }
        } catch (\Exception $e) {
            return;
        }

        foreach ($mapping as $id => $ifName) {
            self::$portNameCache[(int) $id] = $ifName;
        }
    }

    /**
     * Clear the port-name cache.
     */
    public static function flushPortNameCache(): void
    {
        self::$portNameCache = [];
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
