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

        // Try to get device name from LibreNMS
        try {
            if (class_exists('\App\Models\Device')) {
                $device = \App\Models\Device::find($this->device_id);
                return $device ? $device->hostname : null;
            }

            // Fallback for older versions
            $device = dbFetchRow("SELECT hostname FROM devices WHERE device_id = ?", [$this->device_id]);
            return $device ? $device['hostname'] : null;
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
            if (class_exists('\App\Models\Device')) {
                $device = \App\Models\Device::find($this->device_id);
                if (!$device) {
                    return 'unknown';
                }

                // Handle both string and numeric status values
                $status = $device->status;
                if (is_numeric($status)) {
                    return (int)$status === 1 ? 'up' : 'down';
                }
                return strtolower($status) === 'up' ? 'up' : 'down';
            }

            // Fallback for older LibreNMS versions
            $device = dbFetchRow("SELECT status FROM devices WHERE device_id = ?", [$this->device_id]);
            if (!$device) {
                return 'unknown';
            }

            // Handle both string and numeric status values
            $status = $device['status'];
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