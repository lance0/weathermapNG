<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\RRD\RRDTool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RrdDataService
{
    private RRDTool $rrdTool;
    private array $portInfoCache = [];
    private array $deviceInfoCache = [];

    public function __construct(RRDTool $rrdTool)
    {
        $this->rrdTool = $rrdTool;
    }
    public function preloadPortInfo(array $portIds): void
    {
        $ids = array_values(array_unique(array_filter($portIds, fn ($id) => $id > 0)));
        if (empty($ids)) {
            return;
        }

        try {
            $rows = class_exists('\\App\\Models\\Port')
                ? \App\Models\Port::whereIn('port_id', $ids)
                    ->get(['device_id', 'ifIndex', 'ifName', 'port_id'])->keyBy('port_id')
                : DB::table('ports')->whereIn('port_id', $ids)
                    ->get(['device_id', 'ifIndex', 'ifName', 'port_id'])->keyBy('port_id');

            foreach ($ids as $id) {
                $this->portInfoCache[$id] = $rows->has($id) ? (array) $rows->get($id) : null;
            }
        } catch (\Exception $e) {
            Log::debug('Failed to preload port info: ' . $e->getMessage());
        }
    }

    public function preloadDeviceInfo(array $deviceIds): void
    {
        $ids = array_values(array_unique(array_filter($deviceIds, fn ($id) => $id > 0)));
        if (empty($ids)) {
            return;
        }

        try {
            $rows = class_exists('\\App\\Models\\Device')
                ? \App\Models\Device::whereIn('device_id', $ids)
                    ->get(['hostname', 'sysName', 'rrd_path'])->keyBy('device_id')
                : DB::table('devices')->whereIn('device_id', $ids)
                    ->get(['hostname', 'sysName', 'rrd_path'])->keyBy('device_id');

            foreach ($ids as $id) {
                $this->deviceInfoCache[$id] = $rows->has($id) ? (array) $rows->get($id) : null;
            }
        } catch (\Exception $e) {
            Log::debug('Failed to preload device info: ' . $e->getMessage());
        }
    }

    public function flushRequestCache(): void
    {
        $this->portInfoCache = [];
        $this->deviceInfoCache = [];
    }

    public function getPortTraffic(int $portId): ?array
    {
        $port = $this->getPortInfo($portId);
        if (!$port) {
            return null;
        }

        $rrdPath = $this->resolvePortRrdPath($port);
        if (!$rrdPath || !file_exists($rrdPath)) {
            return null;
        }

        return $this->fetchTrafficFromRrd($rrdPath);
    }

    private function getPortInfo(int $portId): ?array
    {
        if (array_key_exists($portId, $this->portInfoCache)) {
            return $this->portInfoCache[$portId];
        }

        try {
            $query = class_exists('\\App\\Models\\Port')
                ? \App\Models\Port::select('device_id', 'ifIndex', 'ifName', 'port_id')
                    ->where('port_id', $portId)->first()
                : DB::table('ports')->select('device_id', 'ifIndex', 'ifName', 'port_id')
                    ->where('port_id', $portId)->first();

            return $this->portInfoCache[$portId] = $query ? (array) $query : null;
        } catch (\Exception $e) {
            Log::debug("Failed to get port info for port {$portId}: " . $e->getMessage());
            return null;
        }
    }

    private function resolvePortRrdPath(array $port): ?string
    {
        $config = config('weathermapng.rrd_base');
        $device = $this->getDeviceInfo($port['device_id']);

        if (!$device) {
            return null;
        }

        $hostname = $device['hostname'] ?? $device['sysName'] ?? '';
        if (!$hostname) {
            return null;
        }

        $ifIndex = $port['ifIndex'] ?? '';
        $ifName = $port['ifName'] ?? '';

        // Sanitize ifName for filesystem (LibreNMS replaces / : and spaces with -)
        $sanitizedIfName = preg_replace('/[\/\s:]+/', '-', $ifName);

        // Try different RRD path patterns in order of likelihood
        // LibreNMS standard is: port-{ifName_sanitized}.rrd
        $patterns = [
            "{$config}/{$hostname}/port-{$sanitizedIfName}.rrd",  // LibreNMS standard
            "{$config}/{$hostname}/port-id{$port['port_id']}.rrd", // Fallback by port_id
            "{$config}/{$hostname}/port-{$ifIndex}.rrd",           // Legacy by ifIndex
            "{$config}/{$hostname}/port_{$ifIndex}.rrd",           // Alt legacy format
        ];

        foreach ($patterns as $pattern) {
            if (file_exists($pattern)) {
                return $pattern;
            }
        }

        Log::debug("WeathermapNG: No RRD file found for port {$port['port_id']} ({$ifName}), tried: " . implode(', ', $patterns));
        return null;
    }

    private function getDeviceInfo(int $deviceId): ?array
    {
        if (array_key_exists($deviceId, $this->deviceInfoCache)) {
            return $this->deviceInfoCache[$deviceId];
        }

        try {
            $device = class_exists('\\App\\Models\\Device')
                ? \App\Models\Device::select('hostname', 'sysName', 'rrd_path')
                    ->where('device_id', $deviceId)->first()
                : DB::table('devices')->select('hostname', 'sysName', 'rrd_path')
                    ->where('device_id', $deviceId)->first();

            return $this->deviceInfoCache[$deviceId] = $device ? (array) $device : null;
        } catch (\Exception $e) {
            Log::debug("Failed to get device info for device {$deviceId}: " . $e->getMessage());
            return null;
        }
    }

    private function fetchTrafficFromRrd(string $rrdPath): array
    {
        try {
            $inBps = $this->rrdTool->getLastValue($rrdPath, 'traffic_in');
            $outBps = $this->rrdTool->getLastValue($rrdPath, 'traffic_out');

            return [
                'in' => (int) ($inBps ?? 0),
                'out' => (int) ($outBps ?? 0),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to fetch traffic from RRD {$rrdPath}: " . $e->getMessage());
            return ['in' => 0, 'out' => 0];
        }
    }
}
