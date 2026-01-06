<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

class DeviceMetricsService
{
    public function getCpuUsage(int $deviceId): ?float
    {
        try {
            $cpu = \DB::table('processors')
                ->where('device_id', $deviceId)
                ->avg('processor_usage');

            return $cpu !== null ? round((float) $cpu, 2) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getMemoryUsage(int $deviceId): ?float
    {
        try {
            $mem = \DB::table('mempools')
                ->where('device_id', $deviceId)
                ->avg('mempool_perc');

            return $mem !== null ? round((float) $mem, 2) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getDeviceMetrics(int $deviceId): array
    {
        return [
            'cpu' => $this->getCpuUsage($deviceId),
            'mem' => $this->getMemoryUsage($deviceId),
        ];
    }
}
