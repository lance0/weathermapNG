<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\Cache;
use LibreNMS\Plugins\WeathermapNG\RRD\LibreNMSAPI;

class PortUtilService
{
    private $api;
    private $rrdService;
    private $snmpService;

    public function __construct(
        LibreNMSAPI $api,
        RrdDataService $rrdService,
        SnmpDataService $snmpService
    ) {
        $this->api = $api;
        $this->rrdService = $rrdService;
        $this->snmpService = $snmpService;
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
            $utilization = round(($inBps + $outBps) / $bandwidth * 100, 2);
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
        $cacheKey = "weathermapng.port.{$portId}";
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
        if (config('weathermapng.enable_local_rrd', true)) {
            $rrdData = $this->rrdService->getPortTraffic($portId);
            if ($rrdData) {
                return $rrdData;
            }
        }

        if (config('weathermapng.enable_api_fallback', true)) {
            return $this->fetchFromAPI($portId);
        }

        if (config('weathermapng.snmp.enabled', false)) {
            $snmpData = $this->snmpService->getPortTraffic($portId);
            if ($snmpData) {
                return $snmpData;
            }
        }

        return ['in' => 0, 'out' => 0];
    }

    private function fetchFromAPI(int $portId): array
    {
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
            error_log("PortUtilService API error for port {$portId}: " . $e->getMessage());
            return ['in' => 0, 'out' => 0];
        }
    }

    private function extractLatestValue(?array $data): int
    {
        if (empty($data)) {
            return 0;
        }

        $latest = end($data);
        return (int) ($latest['value'] ?? 0);
    }

    private function fetchDeviceAggregate(int $deviceId): array
    {
        try {
            $inData = $this->api->getDeviceData($deviceId, 'traffic_in', '5m');
            $inSum = array_sum(array_column($inData, 'value'));

            $outData = $this->api->getDeviceData($deviceId, 'traffic_out', '5m');
            $outSum = array_sum(array_column($outData, 'value'));

            return [
                'in' => (int) $inSum,
                'out' => (int) $outSum,
            ];
        } catch (\Exception $e) {
            error_log("PortUtilService API error for device {$deviceId}: " . $e->getMessage());
            return ['in' => 0, 'out' => 0];
        }
    }
}
