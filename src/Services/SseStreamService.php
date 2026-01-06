<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;

class SseStreamService
{
    private $mapDataBuilder;
    private $portUtil;
    private $alertService;

    public function __construct(MapDataBuilder $mapDataBuilder, PortUtilService $portUtil, AlertService $alertService)
    {
        $this->mapDataBuilder = $mapDataBuilder;
        $this->portUtil = $portUtil;
        $this->alertService = $alertService;
    }

    public function stream(Map $map, int $interval, int $maxSeconds): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->stream(
            function () use ($map, $interval, $maxSeconds) {
                $this->configureOutputBuffering();
                $this->streamLoop($map, $interval, $maxSeconds);
            },
            200,
            $this->getResponseHeaders()
        );
    }

    private function configureOutputBuffering(): void
    {
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        @ob_implicit_flush(1);
    }

    private function getResponseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ];
    }

    private function streamLoop(Map $map, int $interval, int $maxSeconds): void
    {
        $start = time();

        while (true) {
            $payload = $this->buildSsePayload($map);
            $this->emitSseEvent($payload);

            if ($this->shouldStopStreaming($start, $maxSeconds)) {
                break;
            }

            sleep($interval);
        }
    }

    private function buildSsePayload(Map $map): array
    {
        return [
            'ts' => time(),
            'links' => $this->mapDataBuilder->buildLinkData($map),
            'nodes' => $this->buildSseNodeData($map),
            'alerts' => $this->mapDataBuilder->buildAlertData($map),
        ];
    }

    private function buildSseNodeData(Map $map): array
    {
        $nodeData = [];
        $portsByNode = $this->buildPortsByNode($map);
        $linkData = $this->mapDataBuilder->buildLinkData($map);

        foreach ($map->nodes as $node) {
            $nodeData[$node->id] = $this->buildNodeWithMetrics($node, $portsByNode, $linkData);
        }

        return $nodeData;
    }

    private function buildNodeWithMetrics(Node $node, array $portsByNode, array $linkData): array
    {
        $portIds = $portsByNode[$node->id] ?? [];
        $trafficData = $this->aggregateNodeTraffic($node, $portIds, $linkData);
        $metrics = $this->getDeviceMetrics($node);
        $status = $this->getNodeStatus($node);

        return [
            'status' => $status,
            'metrics' => $metrics,
            'traffic' => $trafficData,
        ];
    }

    private function getDeviceMetrics(Node $node): array
    {
        if (!$node->device_id) {
            return ['cpu' => null, 'mem' => null];
        }

        return [
            'cpu' => $this->getCpuUsage($node->device_id),
            'mem' => $this->getMemoryUsage($node->device_id),
        ];
    }

    private function getCpuUsage(int $deviceId): ?float
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

    private function getMemoryUsage(int $deviceId): ?float
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

    private function getNodeStatus(Node $node): string
    {
        if (!$node->device_id) {
            return 'unknown';
        }

        try {
            $device = $this->fetchDevice($node->device_id);
            if ($device) {
                return ($device->status ?? 0) ? 'up' : 'down';
            }
        } catch (\Exception $e) {
        }

        return 'unknown';
    }

    private function fetchDevice(int $deviceId)
    {
        if (class_exists('App\\Models\\Device')) {
            return \App\Models\Device::find($deviceId);
        }

        return \DB::table('devices')->where('device_id', $deviceId)->first();
    }

    private function aggregateNodeTraffic(Node $node, array $portIds, array $linkData): array
    {
        $traffic = $this->sumPortTraffic($portIds);

        if ($traffic['sum'] === 0) {
            $traffic = $this->sumLinkTraffic($node, $linkData);
        }

        if ($traffic['sum'] === 0 && $node->device_id) {
            $traffic = $this->getDeviceTraffic($node->device_id);
        }

        if ($traffic['sum'] === 0 && !$node->device_id && $node->label) {
            $traffic = $this->guessDeviceTraffic($node->label);
        }

        return $traffic;
    }

    private function sumPortTraffic(array $portIds): array
    {
        $inSum = 0;
        $outSum = 0;

        foreach (array_unique($portIds) as $portId) {
            try {
                $portData = $this->portUtil->getPortData((int) $portId);
                $inSum += (int) ($portData['in'] ?? 0);
                $outSum += (int) ($portData['out'] ?? 0);
            } catch (\Throwable $e) {
            }
        }

        return [
            'in_bps' => $inSum,
            'out_bps' => $outSum,
            'sum_bps' => $inSum + $outSum,
            'source' => ($inSum + $outSum) > 0 ? 'ports' : 'none',
        ];
    }

    private function sumLinkTraffic(Node $node, array $linkData): array
    {
        $inSum = 0;
        $outSum = 0;

        foreach ($linkData as $linkId => $linkInfo) {
            if ($this->isLinkConnectedToNode($node, $linkId)) {
                $inSum += (int) ($linkInfo['in_bps'] ?? 0);
                $outSum += (int) ($linkInfo['out_bps'] ?? 0);
            }
        }

        return [
            'in_bps' => $inSum,
            'out_bps' => $outSum,
            'sum_bps' => $inSum + $outSum,
            'source' => ($inSum + $outSum) > 0 ? 'links' : 'none',
        ];
    }

    private function isLinkConnectedToNode(Node $node, int $linkId): bool
    {
        $link = collect($node->map->links)->first(fn($lnk) => $lnk->id == $linkId);
        return $link && ($link->src_node_id == $node->id || $link->dst_node_id == $node->id);
    }

    private function getDeviceTraffic(int $deviceId): array
    {
        $agg = $this->portUtil->deviceAggregateBits($deviceId, 24);
        $inSum = (int) ($agg['in'] ?? 0);
        $outSum = (int) ($agg['out'] ?? 0);

        return [
            'in_bps' => $inSum,
            'out_bps' => $outSum,
            'sum_bps' => $inSum + $outSum,
            'source' => ($inSum + $outSum) > 0 ? 'device' : 'none',
        ];
    }

    private function guessDeviceTraffic(string $label): array
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

                return [
                    'in_bps' => $inSum,
                    'out_bps' => $outSum,
                    'sum_bps' => $inSum + $outSum,
                    'source' => ($inSum + $outSum) > 0 ? 'device_guess' : 'none',
                ];
            }
        } catch (\Throwable $e) {
        }

        return ['in_bps' => 0, 'out_bps' => 0, 'sum_bps' => 0, 'source' => 'none'];
    }

    private function buildPortsByNode(Map $map): array
    {
        $portsByNode = [];
        foreach ($map->links as $link) {
            $this->addPortToNode($portsByNode, $link->src_node_id, $link->port_id_a);
            $this->addPortToNode($portsByNode, $link->dst_node_id, $link->port_id_b);
        }
        return $portsByNode;
    }

    private function addPortToNode(array &$portsByNode, ?int $nodeId, ?int $portId): void
    {
        if ($nodeId && $portId) {
            $portsByNode[$nodeId] = $portsByNode[$nodeId] ?? [];
            $portsByNode[$nodeId][] = (int) $portId;
        }
    }

    private function emitSseEvent(array $payload): void
    {
        echo 'data: ' . json_encode($payload) . "\n\n";
        @ob_flush();
        @flush();
    }

    private function shouldStopStreaming(int $startTime, int $maxSeconds): bool
    {
        return connection_aborted() || (time() - $startTime) >= $maxSeconds;
    }
}
