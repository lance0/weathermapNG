<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\Cache;

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
                if (class_exists('\App\Models\Port')) {
                    return \App\Models\Port::where('device_id', $deviceId)
                        ->where('deleted', 0)
                        ->select('port_id', 'ifName', 'ifIndex', 'ifOperStatus', 'ifAdminStatus')
                        ->orderBy('ifName')
                        ->get()
                        ->toArray();
                }

                // Fallback for older versions
                $ports = dbFetchRows("
                    SELECT port_id, ifName, ifIndex, ifOperStatus, ifAdminStatus
                    FROM ports
                    WHERE device_id = ? AND deleted = 0
                    ORDER BY ifName
                ", [$deviceId]);

                return $ports ?: [];
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
     * Get device details by ID
     */
    public function getDevice(int $deviceId): ?array
    {
        $cacheKey = "weathermapng.device.{$deviceId}";
        $cacheTtl = config('weathermapng.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($deviceId) {
            try {
                if (class_exists('\App\Models\Device')) {
                    $device = \App\Models\Device::find($deviceId);
                    return $device ? $device->toArray() : null;
                }

                // Fallback
                $device = dbFetchRow("SELECT * FROM devices WHERE device_id = ?", [$deviceId]);
                return $device ?: null;
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    /**
     * Get port details by ID
     */
    public function getPort(int $portId): ?array
    {
        $cacheKey = "weathermapng.port.{$portId}";
        $cacheTtl = config('weathermapng.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($portId) {
            try {
                if (class_exists('\App\Models\Port')) {
                    $port = \App\Models\Port::find($portId);
                    return $port ? $port->toArray() : null;
                }

                // Fallback
                $port = dbFetchRow("SELECT * FROM ports WHERE port_id = ?", [$portId]);
                return $port ?: null;
            } catch (\Exception $e) {
                return null;
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

    /**
     * Get device count
     */
    public function getDeviceCount(): int
    {
        try {
            if (class_exists('\App\Models\Device')) {
                return \App\Models\Device::where('disabled', 0)
                    ->where('ignore', 0)
                    ->count();
            }

            // Fallback
            $count = dbFetchCell("SELECT COUNT(*) FROM devices WHERE disabled = 0 AND ignore = 0");
            return (int) $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get port count for a device
     */
    public function getPortCount(int $deviceId): int
    {
        try {
            if (class_exists('\App\Models\Port')) {
                return \App\Models\Port::where('device_id', $deviceId)
                    ->where('deleted', 0)
                    ->count();
            }

            // Fallback
            $count = dbFetchCell("SELECT COUNT(*) FROM ports WHERE device_id = ? AND deleted = 0", [$deviceId]);
            return (int) $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Clear all caches (useful for testing or forced refresh)
     */
    public function clearCaches(): void
    {
        $cacheKeys = [
            'weathermapng.all_devices',
            'weathermapng.device_search.*',
            'weathermapng.device.*',
            'weathermapng.device_ports.*',
            'weathermapng.port.*',
        ];

        foreach ($cacheKeys as $pattern) {
            // Note: This is a simplified cache clearing
            // In production, you might want to use a more sophisticated approach
            Cache::forget($pattern);
        }
    }
}
