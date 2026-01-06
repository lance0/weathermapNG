<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\PortUtilService;

class DeviceDataService
{
    private $portUtil;

    public function __construct(PortUtilService $portUtil)
    {
        $this->portUtil = $portUtil;
    }

    public function getNodeStatus(Node $node): string
    {
        if (!$node->device_id) {
            return 'unknown';
        }

        $device = $this->fetchDevice($node->device_id);
        return $this->parseDeviceStatus($device);
    }

    public function getDeviceTraffic(int $deviceId): array
    {
        $agg = $this->portUtil->deviceAggregateBits($deviceId, 24);
        $inSum = (int) ($agg['in'] ?? 0);
        $outSum = (int) ($agg['out'] ?? 0);

        return $this->formatTrafficData($inSum, $outSum, 'device');
    }

    public function guessDeviceTraffic(string $label): array
    {
        try {
            $row = \DB::table('devices')
                ->select('device_id')
                ->where('hostname', $label)
                ->orWhere('sysName', $label)
                ->first();

            if ($row && isset($row->device_id)) {
                $agg = $this->portUtil->deviceAggregateBits((int) $row->device_id, 24);
                $inSum = (int) ($agg['in'] ?? 0);
                $outSum = (int) ($agg['out'] ?? 0);

                return $this->formatTrafficData($inSum, $outSum, 'device_guess');
            }
        } catch (\Throwable $e) {
        }

        return $this->formatTrafficData(0, 0, 'none');
    }

    private function fetchDevice(int $deviceId)
    {
        if (class_exists('\\App\\Models\\Device')) {
            return \App\Models\Device::find($deviceId);
        }

        return \DB::table('devices')->where('device_id', $deviceId)->first();
    }

    private function parseDeviceStatus($device): string
    {
        if (!$device) {
            return 'unknown';
        }

        $status = is_object($device) ? ($device->status ?? null) : ($device['status'] ?? null);

        return $this->convertStatusToString($status);
    }

    private function convertStatusToString($status): string
    {
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
