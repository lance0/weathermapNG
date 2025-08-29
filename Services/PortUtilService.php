<?php

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\Cache;
use LibreNMS\Plugins\WeathermapNG\RRD\RRDTool;
use LibreNMS\Plugins\WeathermapNG\RRD\LibreNMSAPI;

class PortUtilService
{
    private $rrdTool;
    private $api;

    public function __construct()
    {
        $this->rrdTool = new RRDTool();
        $this->api = new LibreNMSAPI();
    }

    /**
     * Get link utilization data for a link with two ports
     */
    public function linkUtilBits(array $link): array
    {
        $portA = $link['port_id_a'] ?? null;
        $portB = $link['port_id_b'] ?? null;
        $bandwidth = $link['bandwidth_bps'] ?? null;

        if (!$portA && !$portB) {
            return [
                'in_bps' => 0,
                'out_bps' => 0,
                'pct' => null,
                'err' => 'No ports configured',
            ];
        }

        $dataA = $portA ? $this->getPortData($portA) : ['in' => 0, 'out' => 0];
        $dataB = $portB ? $this->getPortData($portB) : ['in' => 0, 'out' => 0];

        // Combine data from both ports (bidirectional)
        $inBps = max($dataA['in'], $dataB['out']); // Higher inbound
        $outBps = max($dataA['out'], $dataB['in']); // Higher outbound

        $utilization = null;
        if ($bandwidth && $bandwidth > 0) {
            $utilization = round(($inBps + $outBps) / $bandwidth * 100, 2);
        }

        return [
            'in_bps' => $inBps,
            'out_bps' => $outBps,
            'pct' => $utilization,
            'err' => null,
        ];
    }

    /**
     * Get utilization data for a single port
     */
    public function getPortData(int $portId): array
    {
        $cacheKey = "weathermapng.port.{$portId}";
        $cacheTtl = config('weathermapng.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($portId) {
            return $this->fetchPortData($portId);
        });
    }

    /**
     * Fetch port data from RRD or API
     */
    private function fetchPortData(int $portId): array
    {
        // Get port information
        $port = $this->getPortInfo($portId);
        if (!$port) {
            return ['in' => 0, 'out' => 0];
        }

        // Try RRD first
        if (config('weathermapng.enable_local_rrd', true)) {
            $rrdData = $this->fetchFromRRD($port);
            if ($rrdData) {
                return $rrdData;
            }
        }

        // Fallback to API
        if (config('weathermapng.enable_api_fallback', true)) {
            return $this->fetchFromAPI($portId);
        }

        return ['in' => 0, 'out' => 0];
    }

    /**
     * Fetch data from local RRD files
     */
    private function fetchFromRRD($port): ?array
    {
        try {
            $device = $this->getDeviceInfo($port->device_id ?? $port['device_id']);
            if (!$device || !isset($device->rrd_path)) {
                return null;
            }

            $rrdPath = $device->rrd_path . '/port-' . ($port->ifIndex ?? $port['ifIndex']) . '.rrd';

            if (!file_exists($rrdPath)) {
                return null;
            }

            // Get current values
            $inBps = $this->rrdTool->getLastValue($rrdPath, 'traffic_in');
            $outBps = $this->rrdTool->getLastValue($rrdPath, 'traffic_out');

            if ($inBps === null || $outBps === null) {
                return null;
            }

            return [
                'in' => (int) $inBps,
                'out' => (int) $outBps,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch data from LibreNMS API
     */
    private function fetchFromAPI(int $portId): array
    {
        // In test environment, return mock data to avoid timeouts
        if (defined('TESTING') && TESTING) {
            return [
                'in' => rand(1000000, 50000000),
                'out' => rand(1000000, 50000000),
            ];
        }

        try {
            $inData = $this->api->getPortMetricByPortId($portId, 'traffic_in', '5m');
            $inBps = $this->extractLatestValue($inData);

            $outData = $this->api->getPortMetricByPortId($portId, 'traffic_out', '5m');
            $outBps = $this->extractLatestValue($outData);

            return [
                'in' => (int) $inBps,
                'out' => (int) $outBps,
            ];
        } catch (\Exception $e) {
            // Log error but don't fail - return zeros
            error_log("PortUtilService API error for port {$portId}: " . $e->getMessage());
            return ['in' => 0, 'out' => 0];
        }
    }
}

    /**
     * Extract the latest value from API data
     */
private function extractLatestValue(array $data): float
{
    if (empty($data)) {
        return 0.0;
    }

    // Get the most recent entry
    $latest = end($data);
    return (float) ($latest['value'] ?? 0);
}

    /**
     * Get port information
     */
private function getPortInfo(int $portId)
{
    try {
        if (class_exists('\App\Models\Port')) {
            return \App\Models\Port::find($portId);
        }

        // Fallback
        return dbFetchRow("SELECT * FROM ports WHERE port_id = ?", [$portId]);
    } catch (\Exception $e) {
        return null;
    }
}

    /**
     * Get device information
     */
private function getDeviceInfo(int $deviceId)
{
    try {
        if (class_exists('\App\Models\Device')) {
            return \App\Models\Device::find($deviceId);
        }

        // Fallback
        return dbFetchRow("SELECT * FROM devices WHERE device_id = ?", [$deviceId]);
    } catch (\Exception $e) {
        return null;
    }
}

    /**
     * Get historical data for a port
     */
public function getPortHistory(int $portId, string $metric, string $period = '1h'): array
{
    $cacheKey = "weathermapng.port_history.{$portId}.{$metric}.{$period}";
    $cacheTtl = config('weathermapng.cache_ttl', 300);

    return Cache::remember($cacheKey, $cacheTtl, function () use ($portId, $metric, $period) {
        $port = $this->getPortInfo($portId);
        if (!$port) {
            return [];
        }

        // Try RRD first
        if (config('weathermapng.enable_local_rrd', true)) {
            $rrdData = $this->fetchHistoryFromRRD($port, $metric, $period);
            if (!empty($rrdData)) {
                return $rrdData;
            }
        }

        // Fallback to API
        if (config('weathermapng.enable_api_fallback', true)) {
            return $this->api->getPortData($portId, $metric, $period);
        }

        return [];
    });
}

    /**
     * Fetch historical data from RRD
     */
private function fetchHistoryFromRRD($port, string $metric, string $period): array
{
    try {
        $device = $this->getDeviceInfo($port->device_id ?? $port['device_id']);
        if (!$device || !isset($device->rrd_path)) {
            return [];
        }

        $rrdPath = $device->rrd_path . '/port-' . ($port->ifIndex ?? $port['ifIndex']) . '.rrd';

        if (!file_exists($rrdPath)) {
            return [];
        }

        return $this->rrdTool->fetch($rrdPath, $metric, $period);
    } catch (\Exception $e) {
        return [];
    }
}
}
