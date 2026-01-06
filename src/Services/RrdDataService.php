<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\RRD\RRDTool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RrdDataService
{
    private $rrdTool;

    public function __construct(RRDTool $rrdTool)
    {
        $this->rrdTool = $rrdTool;
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
        try {
            $query = class_exists('\\App\\Models\\Port')
                ? \App\Models\Port::select('device_id', 'ifIndex', 'port_id')->where('port_id', $portId)->first()
                : \DB::table('ports')->select('device_id', 'ifIndex', 'port_id')->where('port_id', $portId)->first();

            return $query ? (array) $query : null;
        } catch (\Exception $e) {
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

        $hostname = $device->hostname ?? $device['sysName'] ?? '';
        $ifIndex = $port['ifIndex'];

        // Try different RRD path patterns
        $patterns = [
            "{$config}/{$hostname}/port-id{$port['port_id']}.rrd",
            "{$config}/{$hostname}/port-{$ifIndex}.rrd",
            "{$config}/{$hostname}/port_{$ifIndex}.rrd",
        ];

        foreach ($patterns as $pattern) {
            if (file_exists($pattern)) {
                return $pattern;
            }
        }

        return null;
    }

    private function getDeviceInfo(int $deviceId): ?array
    {
        try {
            $device = class_exists('\\App\\Models\\Device')
                ? \App\Models\Device::select('hostname', 'sysName', 'rrd_path')
                    ->where('device_id', $deviceId)->first()
                : \DB::table('devices')->select('hostname', 'sysName', 'rrd_path')
                    ->where('device_id', $deviceId)->first();

            return $device ? (array) $device : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchTrafficFromRrd(string $rrdPath): array
    {
        try {
            $inBps = $this->rrdTool->getLastValue($rrdPath, 'traffic_in');
            $outBps = $this->rrdTool->getLastValue($rrdPath, 'traffic_out');

            if ($inBps === null || $outBps === null) {
                return ['in' => 0, 'out' => 0];
            }

            return [
                'in' => (int) $inBps,
                'out' => (int) $outBps,
            ];
        } catch (\Exception $e) {
            return ['in' => 0, 'out' => 0];
        }
    }
}
