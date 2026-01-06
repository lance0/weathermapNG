<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Node;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceDataService
{
    private $portUtil;
    private $deviceMetrics;

    public function __construct(PortUtilService $portUtil, DeviceMetricsService $deviceMetrics)
    {
        $this->portUtil = $portUtil;
        $this->deviceMetrics = $deviceMetrics;
    }

    public function getNodeStatus(Node $node): string
    {
        if (!$node->device_id) {
            return 'unknown';
        }

        try {
            $device = $this->fetchDevice((int) $node->device_id);
            return $this->parseDeviceStatus($device);
        } catch (\Exception $e) {
            Log::debug("Failed to get status for device {$node->device_id}: " . $e->getMessage());
            return 'unknown';
        }
    }

    public function getNodeMetrics(Node $node): array
    {
        if (!$node->device_id) {
            return ['cpu' => null, 'mem' => null];
        }

        return $this->deviceMetrics->getDeviceMetrics((int) $node->device_id);
    }

    public function getDeviceTraffic(int $deviceId): array
    {
        try {
            $agg = $this->portUtil->deviceAggregateBits($deviceId);
            return $this->formatTrafficData((int) ($agg['in'] ?? 0), (int) ($agg['out'] ?? 0), 'device');
        } catch (\Exception $e) {
            Log::error("Failed to get traffic for device {$deviceId}: " . $e->getMessage());
            return $this->formatTrafficData(0, 0, 'none');
        }
    }

    public function guessDeviceTraffic(string $label): array
    {
        try {
            $row = DB::table('devices')
                ->select('device_id')
                ->where('hostname', $label)
                ->orWhere('sysName', $label)
                ->first();

            if ($row && isset($row->device_id)) {
                return $this->getDeviceTraffic((int) $row->device_id);
            }
        } catch (\Throwable $e) {
            Log::debug("Failed to guess traffic for label '{$label}': " . $e->getMessage());
        }

        return $this->formatTrafficData(0, 0, 'none');
    }

    private function fetchDevice(int $deviceId)
    {
        if (class_exists('\\App\\Models\\Device')) {
            return \App\Models\Device::find($deviceId);
        }

        return DB::table('devices')->where('device_id', $deviceId)->first();
    }

    private function parseDeviceStatus($device): string
    {
        if (!$device) {
            return 'unknown';
        }

        $status = is_object($device) ? ($device->status ?? null) : ($device['status'] ?? null);

        if (is_numeric($status)) {
            return (int) $status === 1 ? 'up' : 'down';
        }

        $statusLower = strtolower((string) $status);
        return $statusLower === 'up' ? 'up' : 'down';
    }

    private function formatTrafficData(int $inSum, int $outSum, string $source): array
    {
        return [
            'in_bps' => $inSum,
            'out_bps' => $outSum,
            'sum_bps' => $inSum + $outSum,
            'source' => ($inSum + $outSum) > 0 ? $source : 'none',
        ];
    }
}
