<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PortUtilService
{
    private RrdDataService $rrdService;

    public function __construct(RrdDataService $rrdService)
    {
        $this->rrdService = $rrdService;
    }

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

        $inBps = max($dataA['in'], $dataB['out']);
        $outBps = max($dataA['out'], $dataB['in']);

        $utilization = null;
        if ($bandwidth && $bandwidth > 0) {
            // Use max for full-duplex links (both directions can saturate independently)
            $utilization = round(max($inBps, $outBps) / $bandwidth * 100, 2);
        }

        return [
            'in_bps' => $inBps,
            'out_bps' => $outBps,
            'pct' => $utilization,
            'err' => null,
        ];
    }

    public function getPortData(int $portId): array
    {
        // Use distinct cache key to avoid collision with DevicePortLookup
        $cacheKey = "weathermapng.port.traffic.{$portId}";
        $cacheTtl = config('weathermapng.cache_ttl', 300);

        if (!class_exists(Cache::class)) {
            return $this->fetchPortData($portId);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($portId) {
            return $this->fetchPortData($portId);
        });
    }

    public function deviceAggregateBits(int $deviceId): array
    {
        $cacheKey = "weathermapng.device.{$deviceId}.aggregate";
        $cacheTtl = config('weathermapng.cache_ttl', 300);

        if (!class_exists(Cache::class)) {
            return $this->fetchDeviceAggregate($deviceId);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($deviceId) {
            return $this->fetchDeviceAggregate($deviceId);
        });
    }

    private function fetchPortData(int $portId): array
    {
        // RRD is the single source of truth
        $rrdData = $this->rrdService->getPortTraffic($portId);

        if ($rrdData !== null) {
            return $rrdData;
        }

        Log::warning("WeathermapNG: No RRD data for port {$portId}");
        return ['in' => 0, 'out' => 0];
    }

    private function fetchDeviceAggregate(int $deviceId): array
    {
        // Sum traffic from all ports on the device
        // This is a placeholder - would need to query all device ports
        Log::debug("WeathermapNG: Device aggregate not implemented for device {$deviceId}");
        return ['in' => 0, 'out' => 0];
    }
}
