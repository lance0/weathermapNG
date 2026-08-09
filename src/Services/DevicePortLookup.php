<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DevicePortLookup
{
    /**
     * Get all ports for a specific device
     */
    public function portsForDevice(int $deviceId): array
    {
        $cacheKey = "weathermapng.device_ports.{$deviceId}";
        $cacheTtl = config('weathermapng.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($deviceId) {
            try {
                return DB::table('ports')
                    ->where('device_id', $deviceId)
                    ->where('deleted', 0)
                    ->select('port_id', 'ifName', 'ifAlias', 'ifIndex', 'ifOperStatus', 'ifAdminStatus')
                    ->orderBy('ifName')
                    ->get()
                    ->map(fn($row) => (array) $row)
                    ->values()
                    ->all();
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Search devices by hostname or sysName
     */
    public function deviceAutocomplete(string $query, int $limit = 20): array
    {
        if (strlen($query) < 2) {
            return [];
        }

        $cacheKey = "weathermapng.device_search." . md5($query);
        $cacheTtl = config('weathermapng.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($query, $limit) {
            try {
                if (class_exists('\App\Models\Device')) {
                    return \App\Models\Device::where('disabled', 0)
                        ->where('ignore', 0)
                        ->where(function ($q) use ($query) {
                            $q->where('hostname', 'LIKE', "%{$query}%")
                              ->orWhere('sysName', 'LIKE', "%{$query}%");
                        })
                        ->select('device_id', 'hostname', 'sysName')
                        ->orderBy('hostname')
                        ->limit($limit)
                        ->get()
                        ->toArray();
                }

                // Fallback
                $devices = dbFetchRows("
                    SELECT device_id, hostname, sysName
                    FROM devices
                    WHERE disabled = 0 AND ignore = 0
                    AND (hostname LIKE ? OR sysName LIKE ?)
                    ORDER BY hostname
                    LIMIT ?
                ", ["%{$query}%", "%{$query}%", $limit]);

                return $devices ?: [];
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Get all devices (for admin/editor use)
     */
    public function getAllDevices(): array
    {
        $cacheKey = "weathermapng.all_devices";
        $cacheTtl = config('weathermapng.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () {
            try {
                if (class_exists('\App\Models\Device')) {
                    return \App\Models\Device::where('disabled', 0)
                        ->where('ignore', 0)
                        ->select('device_id', 'hostname', 'sysName', 'ip', 'status')
                        ->orderBy('hostname')
                        ->get()
                        ->toArray();
                }

                // Fallback
                $devices = dbFetchRows("
                    SELECT device_id, hostname, sysName, ip, status
                    FROM devices
                    WHERE disabled = 0 AND ignore = 0
                    ORDER BY hostname
                ");

                return $devices ?: [];
            } catch (\Exception $e) {
                return [];
            }
        });
    }
}
