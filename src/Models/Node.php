<?php

namespace LibreNMS\Plugins\WeathermapNG\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Node extends Model
{
    protected $table = 'wmng_nodes';
    protected $fillable = ['map_id', 'label', 'x', 'y', 'device_id', 'meta'];
    protected $casts = [
        'meta' => 'array',
        'device_id' => 'integer',
        'x' => 'float',
        'y' => 'float',
    ];

    /** @var array<int, array<string,mixed>|null> Batch-prefetch cache for device lookups. */
    private static array $deviceCache = [];

    public function map()
    {
        return $this->belongsTo(Map::class);
    }

    public function outgoingLinks()
    {
        return $this->hasMany(Link::class, 'src_node_id');
    }

    public function incomingLinks()
    {
        return $this->hasMany(Link::class, 'dst_node_id');
    }

    /**
     * Flush the static device prefetch cache.
     */
    public static function flushDeviceCache(): void
    {
        self::$deviceCache = [];
    }

    /**
     * Prefetch a set of devices in a single query and cache their array forms.
     *
     * @param array<int> $deviceIds
     */
    public static function preloadDevices(array $deviceIds): void
    {
        $ids = array_values(array_unique(array_filter($deviceIds, fn ($id) => $id !== null && $id !== 0)));
        if (empty($ids)) {
            return;
        }

        $remaining = array_values(array_filter($ids, fn ($id) => !array_key_exists($id, self::$deviceCache)));
        if (empty($remaining)) {
            return;
        }

        if (class_exists('App\\Models\\Device')) {
            $devices = \App\Models\Device::whereIn('device_id', $remaining)
                ->get(['device_id', 'hostname', 'status'])
                ->keyBy('device_id');
            foreach ($devices as $deviceId => $device) {
                self::$deviceCache[$deviceId] = (array) $device;
            }
        } else {
            $devices = DB::table('devices')
                ->whereIn('device_id', $remaining)
                ->get(['device_id', 'hostname', 'status'])
                ->keyBy('device_id');
            foreach ($devices as $deviceId => $device) {
                self::$deviceCache[$deviceId] = (array) $device;
            }
        }

        // Record null for any device that was not found, so single-row lookups don't re-fire.
        foreach ($remaining as $id) {
            self::$deviceCache[$id] ??= null;
        }
    }

    public function getDeviceNameAttribute()
    {
        $device = $this->resolveDevice($this->device_id);
        return $device['hostname'] ?? null;
    }

    public function getStatusAttribute()
    {
        $device = $this->resolveDevice($this->device_id);
        return $this->parseDeviceStatus($device);
    }

    /**
     * Resolve a device row from the static cache, falling back to a single fetch.
     *
     * @return array<string,mixed>|null
     */
    private function resolveDevice(?int $deviceId): ?array
    {
        if ($deviceId === null || $deviceId === 0) {
            return null;
        }

        if (array_key_exists($deviceId, self::$deviceCache)) {
            return self::$deviceCache[$deviceId];
        }

        $device = $this->fetchDevice($deviceId);
        return self::$deviceCache[$deviceId] = $device;
    }

    private function fetchDevice(int $deviceId): ?array
    {
        if (class_exists('App\\Models\\Device')) {
            $device = \App\Models\Device::find($deviceId);
            return $device ? (array) $device : null;
        }

        $row = dbFetchRow("SELECT hostname, status FROM devices WHERE device_id = ?", [$deviceId]);
        return $row ?: null;
    }

    private function parseDeviceStatus($device): string
    {
        if (!$device) {
            return 'unknown';
        }

        $status = is_object($device) ? ($device->status ?? null) : ($device['status'] ?? null);

        return $this->convertStatusToString($status);
    }

    private function convertStatusToString($status): string
    {
        if (is_numeric($status)) {
            return (int) $status === 1 ? 'up' : 'down';
        }

        $statusLower = strtolower($status);
        return $statusLower === 'up' ? 'up' : 'down';
    }

    public function toArray()
    {
        $data = parent::toArray();
        $data['device_name'] = $this->device_name;
        $data['status'] = $this->status;
        return $data;
    }
}
