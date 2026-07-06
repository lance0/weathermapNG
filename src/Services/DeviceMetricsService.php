<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceMetricsService
{
    public function getCpuUsage(int $deviceId): ?float
    {
        try {
            $cpu = DB::table('processors')
                ->where('device_id', $deviceId)
                ->avg('processor_usage');

            return $cpu !== null ? round((float) $cpu, 2) : null;
        } catch (\Exception $e) {
            Log::debug("Failed to fetch CPU for device {$deviceId}: " . $e->getMessage());
            return null;
        }
    }

    public function getMemoryUsage(int $deviceId): ?float
    {
        try {
            $mem = DB::table('mempools')
                ->where('device_id', $deviceId)
                ->avg('mempool_perc');

            return $mem !== null ? round((float) $mem, 2) : null;
        } catch (\Exception $e) {
            Log::debug("Failed to fetch memory for device {$deviceId}: " . $e->getMessage());
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

    /**
     * Fetch CPU and memory metrics for many devices in two queries total.
     *
     * @param array $deviceIds
     * @return array<int, array{cpu: ?float, mem: ?float}>
     */
    public function getMetricsForDevices(array $deviceIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $deviceIds,
            fn ($id) => $id !== null && $id !== 0
        )));

        if (empty($ids)) {
            return [];
        }

        $cpuMap = [];
        try {
            $rows = DB::table('processors')
                ->whereIn('device_id', $ids)
                ->selectRaw('device_id, AVG(processor_usage) as cpu')
                ->groupBy('device_id')
                ->get();
            foreach ($rows as $row) {
                $cpuMap[(int) $row->device_id] = round((float) $row->cpu, 2);
            }
        } catch (\Exception $e) {
            Log::debug('Failed to batch fetch CPU for devices: ' . $e->getMessage());
        }

        $memMap = [];
        try {
            $rows = DB::table('mempools')
                ->whereIn('device_id', $ids)
                ->selectRaw('device_id, AVG(mempool_perc) as mem')
                ->groupBy('device_id')
                ->get();
            foreach ($rows as $row) {
                $memMap[(int) $row->device_id] = round((float) $row->mem, 2);
            }
        } catch (\Exception $e) {
            Log::debug('Failed to batch fetch memory for devices: ' . $e->getMessage());
        }

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = [
                'cpu' => $cpuMap[$id] ?? null,
                'mem' => $memMap[$id] ?? null,
            ];
        }

        return $result;
    }
}
