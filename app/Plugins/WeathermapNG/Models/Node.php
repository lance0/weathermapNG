<?php

namespace App\Plugins\WeathermapNG\Models;

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

        try {
            // Try to get device from LibreNMS database
            $device = \DB::table('devices')->where('device_id', $this->device_id)->first();
            return $device ? $device->hostname : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getStatusAttribute()
    {
        if (!$this->device_id) {
            return 'unknown';
        }

        try {
            $device = \DB::table('devices')->where('device_id', $this->device_id)->first();
            if (!$device) {
                return 'unknown';
            }

            // Handle both string and numeric status values
            $status = $device->status;
            if (is_numeric($status)) {
                return (int)$status === 1 ? 'up' : 'down';
            }
            return strtolower($status) === 'up' ? 'up' : 'down';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    public function toArray()
    {
        $data = parent::toArray();
        $data['device_name'] = $this->device_name;
        $data['status'] = $this->status;
        return $data;
    }
}