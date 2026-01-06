<?php

namespace LibreNMS\Plugins\WeathermapNG\Models;

use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    protected $table = 'wmng_nodes';
    protected $fillable = ['map_id', 'label', 'x', 'y', 'device_id', 'meta'];
    protected $casts = ['meta' => 'array'];

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

    public function getDeviceNameAttribute()
    {
        if (!$this->device_id) {
            return null;
        }

        $device = $this->fetchDevice($this->device_id);
        return $device ? $device->hostname : null;
    }

    public function getStatusAttribute()
    {
        if (!$this->device_id) {
            return 'unknown';
        }

        $device = $this->fetchDevice($this->device_id);
        return $this->parseDeviceStatus($device);
    }

    private function fetchDevice(int $deviceId)
    {
        if (class_exists('App\\Models\\Device')) {
            return \App\Models\Device::find($deviceId);
        }

        return dbFetchRow("SELECT hostname, status FROM devices WHERE device_id = ?", [$deviceId]);
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
