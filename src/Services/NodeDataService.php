<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NodeDataService
{
    private $portUtil;
    private $deviceDataService;
    private $alertService;
    private $linkDataService;

    public function __construct(
        PortUtilService $portUtil,
        DeviceDataService $deviceDataService,
        AlertService $alertService,
        LinkDataService $linkDataService
    ) {
        $this->portUtil = $portUtil;
        $this->deviceDataService = $deviceDataService;
        $this->alertService = $alertService;
        $this->linkDataService = $linkDataService;
    }

    /**
     * Preload batch caches for all devices and ports referenced by a map's
     * nodes and links. Call once before buildNodeData/buildLinkData to turn
     * N+1 accessor queries into 2-3 batch queries.
     */
    public function preloadForMap(Map $map): void
    {
        // Device cache for Node::device_name / Node::status accessors
        $deviceIds = $map->nodes->pluck('device_id')->filter()->unique()->values()->all();
        Node::preloadDevices($deviceIds);

        // Port-name cache for Link::source_port_name / destination_port_name
        $portIds = $map->links->flatMap(fn($l) => [$l->port_id_a, $l->port_id_b])
            ->filter(fn($id) => $id !== null && $id !== 0)
            ->unique()
            ->values()
            ->all();
        Link::preloadPortNames($portIds);

        // RRD port/device info cache for traffic lookups — delegate through
        // PortUtilService so we prime the same RrdDataService instance that
        // getPortData() reads from.
        $this->portUtil->preloadForPorts($portIds, $deviceIds);
    }

    public function buildNodeData(Map $map): array
    {
        $metricsMap = $this->deviceDataService->getNodeMetricsBatch($map->nodes->all());
        $links = $map->links;

        $nodeData = [];
        foreach ($map->nodes as $node) {
            $nodeData[$node->id] = $this->buildNodeWithMetrics(
                $node,
                $metricsMap[$node->id] ?? ['cpu' => null, 'mem' => null],
                $links
            );
        }
        return $nodeData;
    }

    private function buildNodeWithMetrics(Node $node, array $metrics, $links): array
    {
        $status = $this->deviceDataService->getNodeStatus($node);
        $trafficData = $this->aggregateNodeTraffic($node, $links);

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

        // Deterministic, time-smoothed utilization: stable across a few seconds,
        // drifting slowly to look alive. Seeded per-link-id so different links
        // show different loads.
        $phase = $link->id * 0.7;
        $t = time() / 30; // 30-second period
        $baseUtil = 30 + ($link->id % 50);
        $utilization = max(0, min(100, $baseUtil + 15 * sin($t + $phase)));

        // Deterministic per-link asymmetric split (no per-call jitter, but
        // in_bps != out_bps like real traffic). Seeded by link id.
        $split = 42 + ($link->id % 16); // 42..57% in, rest out
        $inBps = (int) ($bandwidth * ($utilization / 100) * ($split / 100));
        $outBps = (int) ($bandwidth * ($utilization / 100) * ((100 - $split) / 100));

        return [
            'in_bps' => $inBps,
            'out_bps' => $outBps,
            'pct' => $utilization,
            'bandwidth' => $bandwidth,
            'source' => 'demo',
        ];
    }
    public function buildAlertData(Map $map): array
    {
        $deviceIds = [];
        foreach ($map->nodes as $node) {
            if ($node->device_id) {
                $deviceIds[] = (int) $node->device_id;
            }
        }
        $deviceIds = array_values(array_unique($deviceIds));

        $deviceAlerts = $this->alertService->deviceAlerts($deviceIds);

        $nodeAlerts = [];
        foreach ($map->nodes as $node) {
            if ($node->device_id && isset($deviceAlerts[(int) $node->device_id])) {
                $nodeAlerts[$node->id] = $deviceAlerts[(int) $node->device_id];
            }
        }

        return [
            'nodes' => $nodeAlerts,
            'links' => $this->linkDataService->buildLinkAlerts($map),
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

            // Sleep in bounded chunks so a large interval can't overshoot maxSeconds.
            $remaining = $maxSeconds - (time() - $start);
            $sleep = min($interval, max(1, $remaining));
            sleep($sleep);
        }
    }

    private function buildSsePayload(Map $map): array
    {
        return [
            'ts' => time(),
            'links' => $this->buildLinkData($map),
            'nodes' => $this->buildNodeData($map),
            'alerts' => $this->buildAlertData($map),
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

    private function aggregateNodeTraffic(Node $node, $links): array
    {
        $demoMode = config('weathermapng.demo_mode', false);

        // In demo mode, generate simulated node traffic
        if ($demoMode) {
            return $this->generateDemoNodeTraffic($node);
        }

        $traffic = $this->sumPortTraffic($node, $links);

        if ($traffic['sum_bps'] === 0 && $node->device_id) {
            $traffic = $this->deviceDataService->getDeviceTraffic((int) $node->device_id);
        }

        if ($traffic['sum_bps'] === 0 && !$node->device_id && $node->label) {
            $traffic = $this->deviceDataService->guessDeviceTraffic($node->label);
        }

        return $traffic;
    }

    private function generateDemoNodeTraffic(Node $node): array
    {
        // Generate realistic traffic based on node type (inferred from label)
        $label = strtolower($node->label ?? '');

        // Core/router nodes have higher traffic
        if (str_contains($label, 'core') || str_contains($label, 'router')) {
            $inMin = 500000000;   $inMax = 2000000000;   $inAmp = 200000000;
            $outMin = 400000000;  $outMax = 1800000000;  $outAmp = 180000000;
        // Switches have medium traffic
        } elseif (str_contains($label, 'switch') || str_contains($label, 'sw')) {
            $inMin = 100000000;   $inMax = 800000000;    $inAmp = 100000000;
            $outMin = 80000000;   $outMax = 700000000;   $outAmp = 90000000;
        // Servers have moderate traffic
        } elseif (str_contains($label, 'server') || str_contains($label, 'srv') || str_contains($label, 'db')) {
            $inMin = 50000000;    $inMax = 400000000;    $inAmp = 50000000;
            $outMin = 40000000;   $outMax = 350000000;   $outAmp = 40000000;
        // Firewalls aggregate traffic
        } elseif (str_contains($label, 'firewall') || str_contains($label, 'fw')) {
            $inMin = 200000000;   $inMax = 1000000000;   $inAmp = 120000000;
            $outMin = 180000000;  $outMax = 900000000;   $outAmp = 100000000;
        // Default for other nodes
        } else {
            $inMin = 10000000;    $inMax = 200000000;    $inAmp = 30000000;
            $outMin = 8000000;    $outMax = 180000000;   $outAmp = 25000000;
        }

        // Deterministic base within the band, seeded per-node-id so different
        // nodes show different loads. Slow sine modulation drifts the value
        // over a 30-second period for a "live" feel without per-call jitter.
        $inRange = max(1, $inMax - $inMin);
        $outRange = max(1, $outMax - $outMin);
        $seed = crc32($node->label . $node->id);
        $phase = $node->id * 0.3;
        $t = time() / 30;

        $baseIn = $inMin + ($seed % $inRange);
        $baseOut = $outMin + (($seed >> 8) % $outRange);

        $inBps = (int) max(0, $baseIn + $inAmp * sin($t + $phase));
        $outBps = (int) max(0, $baseOut + $outAmp * sin($t + $phase + 1.1));

        return [
            'in_bps' => $inBps,
            'out_bps' => $outBps,
            'sum_bps' => $inBps + $outBps,
            'source' => 'demo',
        ];
    }

    private function sumPortTraffic(Node $node, $links): array
    {
        $inSum = 0;
        $outSum = 0;

        foreach ($links as $link) {
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
