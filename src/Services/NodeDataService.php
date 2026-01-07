<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NodeDataService
{
    private $portUtil;
    private $deviceDataService;

    public function __construct(PortUtilService $portUtil, DeviceDataService $deviceDataService)
    {
        $this->portUtil = $portUtil;
        $this->deviceDataService = $deviceDataService;
    }

    public function buildNodeData(Map $map): array
    {
        $nodeData = [];
        foreach ($map->nodes as $node) {
            $nodeData[$node->id] = $this->buildNodeWithMetrics($node);
        }
        return $nodeData;
    }

    private function buildNodeWithMetrics(Node $node): array
    {
        $status = $this->deviceDataService->getNodeStatus($node);
        $metrics = $this->deviceDataService->getNodeMetrics($node);
        $trafficData = $this->aggregateNodeTraffic($node);

        return [
            'status' => $status,
            'metrics' => $metrics,
            'traffic' => $trafficData,
        ];
    }

    public function buildLinkData(Map $map): array
    {
        $linkData = [];
        $demoMode = config('weathermapng.demo_mode', false);

        foreach ($map->links as $link) {
            // If demo mode or no real ports, generate simulated data
            if ($demoMode || (!$link->port_id_a && !$link->port_id_b)) {
                $linkData[$link->id] = $this->generateDemoLinkData($link);
            } else {
                $linkData[$link->id] = $this->portUtil->linkUtilBits([
                    'port_id_a' => $link->port_id_a,
                    'port_id_b' => $link->port_id_b,
                    'bandwidth_bps' => $link->bandwidth_bps,
                ]);
            }
        }
        return $linkData;
    }

    private function generateDemoLinkData($link): array
    {
        $bandwidth = $link->bandwidth_bps ?: 1000000000; // Default 1Gbps

        // Generate realistic-looking traffic (10-85% utilization with some variance)
        $baseUtil = rand(10, 85);
        $variance = rand(-5, 5);
        $utilization = max(0, min(100, $baseUtil + $variance));

        $inBps = (int) ($bandwidth * ($utilization / 100) * (rand(40, 60) / 100));
        $outBps = (int) ($bandwidth * ($utilization / 100) * (rand(40, 60) / 100));

        return [
            'in_bps' => $inBps,
            'out_bps' => $outBps,
            'pct' => $utilization,
            'bandwidth' => $bandwidth,
            'source' => 'demo',
        ];
    }

    public function buildAlertData(): array
    {
        // Placeholder for future implementation
        return [
            'nodes' => [],
            'links' => [],
        ];
    }

    public function stream(Map $map, int $interval, int $maxSeconds): StreamedResponse
    {
        return \response()->stream(
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
            'links' => $this->buildLinkData($map),
            'nodes' => $this->buildNodeData($map),
            'alerts' => $this->buildAlertData(),
        ];
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

    private function aggregateNodeTraffic(Node $node): array
    {
        $traffic = $this->sumPortTraffic($node);

        if ($traffic['sum_bps'] === 0 && $node->device_id) {
            $traffic = $this->deviceDataService->getDeviceTraffic((int) $node->device_id);
        }

        if ($traffic['sum_bps'] === 0 && !$node->device_id && $node->label) {
            $traffic = $this->deviceDataService->guessDeviceTraffic($node->label);
        }

        return $traffic;
    }

    private function sumPortTraffic(Node $node): array
    {
        $inSum = 0;
        $outSum = 0;

        foreach ($node->map->links as $link) {
            if ($link->src_node_id == $node->id && $link->port_id_a) {
                $portData = $this->portUtil->getPortData((int) $link->port_id_a);
                $inSum += (int) ($portData['in'] ?? 0);
                $outSum += (int) ($portData['out'] ?? 0);
            }
            if ($link->dst_node_id == $node->id && $link->port_id_b) {
                $portData = $this->portUtil->getPortData((int) $link->port_id_b);
                $inSum += (int) ($portData['out'] ?? 0);
                $outSum += (int) ($portData['in'] ?? 0);
            }
        }

        return [
            'in_bps' => $inSum,
            'out_bps' => $outSum,
            'sum_bps' => $inSum + $outSum,
            'source' => ($inSum + $outSum) > 0 ? 'ports' : 'none',
        ];
    }
}
