<?php
// lib/DataSource.php
namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Support\Facades\Cache;
use LibreNMS\Plugins\WeathermapNG\RRD\RRDTool;
use LibreNMS\Plugins\WeathermapNG\RRD\LibreNMSAPI;

class DataSource
{
    public static function getDevices()
    {
        return Cache::remember('weathermapng.devices', config('weathermapng.cache_ttl', 300), function () {
            try {
                // Try to get devices from LibreNMS database
                if (class_exists('\App\Models\Device')) {
                    return \App\Models\Device::select('device_id', 'hostname', 'sysName', 'rrd_path')->get();
                }

                // Fallback for older LibreNMS versions
                $devices = dbFetchRows("SELECT device_id, hostname, sysName FROM devices WHERE disabled = 0 AND ignore = 0");
                return collect($devices)->map(function($device) {
                    return (object) $device;
                });
            } catch (\Exception $e) {
                return collect([]);
            }
        });
    }

    public static function getInterfaces($deviceId)
    {
        return Cache::remember("weathermapng.interfaces.{$deviceId}", config('weathermapng.cache_ttl', 300), function () use ($deviceId) {
            try {
                if (class_exists('\App\Models\Port')) {
                    return \App\Models\Port::where('device_id', $deviceId)
                        ->select('port_id', 'ifName', 'ifIndex', 'ifOperStatus')
                        ->get();
                }

                // Fallback for older versions
                $interfaces = dbFetchRows("SELECT port_id, ifName, ifIndex, ifOperStatus FROM ports WHERE device_id = ?", [$deviceId]);
                return collect($interfaces)->map(function($interface) {
                    return (object) $interface;
                });
            } catch (\Exception $e) {
                return collect([]);
            }
        });
    }

    public static function getRRDData($deviceId, $interfaceId, $metric = 'traffic_in', $period = '1h')
    {
        $cacheKey = "weathermapng.rrd.{$deviceId}.{$interfaceId}.{$metric}.{$period}";

        return Cache::remember($cacheKey, config('weathermapng.cache_ttl', 300), function () use ($deviceId, $interfaceId, $metric, $period) {
            if (config('weathermapng.enable_local_rrd', true)) {
                $data = self::fetchLocalRRD($deviceId, $interfaceId, $metric, $period);
                if (!empty($data)) {
                    return $data;
                }
            }

            if (config('weathermapng.enable_api_fallback', true)) {
                return self::fallbackToAPI($deviceId, $interfaceId, $metric, $period);
            }

            return [];
        });
    }

    private static function fetchLocalRRD($deviceId, $interfaceId, $metric, $period)
    {
        try {
            $device = self::getDeviceById($deviceId);
            $interface = self::getInterfaceById($interfaceId);

            if (!$device || !$interface) {
                return [];
            }

            $rrdPath = $device->rrd_path . '/port-' . $interface->ifIndex . '.rrd';

            if (!file_exists($rrdPath)) {
                return [];
            }

            $rrdTool = new RRDTool();
            return $rrdTool->fetch($rrdPath, $metric, $period);

        } catch (\Exception $e) {
            return [];
        }
    }

    private static function fallbackToAPI($deviceId, $interfaceId, $metric, $period)
    {
        try {
            $api = new LibreNMSAPI();
            return $api->getPortData($deviceId, $interfaceId, $metric, $period);
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function getDeviceById($deviceId)
    {
        $devices = self::getDevices();
        return $devices->firstWhere('device_id', $deviceId);
    }

    private static function getInterfaceById($interfaceId)
    {
        try {
            if (class_exists('\App\Models\Port')) {
                return \App\Models\Port::find($interfaceId);
            }

            $interface = dbFetchRow("SELECT * FROM ports WHERE port_id = ?", [$interfaceId]);
            return $interface ? (object) $interface : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getDeviceStatus($deviceId)
    {
        $device = self::getDeviceById($deviceId);
        return $device ? 'up' : 'unknown';
    }

    public static function getInterfaceStatus($interfaceId)
    {
        $interface = self::getInterfaceById($interfaceId);
        if (!$interface) {
            return 'unknown';
        }

        return $interface->ifOperStatus === 'up' ? 'up' : 'down';
    }
}