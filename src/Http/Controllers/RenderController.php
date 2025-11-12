<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;
use LibreNMS\Plugins\WeathermapNG\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RenderController
{
    public function json(Map $map)
    {
        return response()->json($map->toJsonModel());
    }

    public function live(Map $map, PortUtilService $svc, AlertService $alerts)
    {
        $out = [
            'ts' => time(),
            'links' => [],
            'nodes' => [],
            'alerts' => [ 'nodes' => [], 'links' => [] ],
        ];

        $out['links'] = $this->buildLinkUtilizationData($map, $svc);
        $deviceIds = $this->collectDeviceIdsFromNodes($map);
        $devAlerts = $alerts->deviceAlerts($deviceIds);
        $portsByNode = $this->buildPortsByNodeMapping($map);

        $out['nodes'] = $this->buildNodeStatusData($map, $portsByNode, $out['links'], $svc);
        $out['alerts'] = $this->buildCompleteAlertData($map, $devAlerts, $alerts);

        return response()->json($out);
    }

    private function buildLinkUtilizationData(Map $map, PortUtilService $svc): array
    {
        $linkData = [];
        foreach ($map->links as $link) {
            $linkData[$link->id] = $svc->linkUtilBits([
                'port_id_a' => $link->port_id_a,
                'port_id_b' => $link->port_id_b,
                'bandwidth_bps' => $link->bandwidth_bps,
            ]);
        }
        return $linkData;
    }

    private function collectDeviceIdsFromNodes(Map $map): array
    {
        $deviceIds = [];
        foreach ($map->nodes as $node) {
            if ($node->device_id) {
                $deviceIds[] = (int) $node->device_id;
            }
        }
        return array_values(array_unique($deviceIds));
    }

    private function buildPortsByNodeMapping(Map $map): array
    {
        $portsByNode = [];
        foreach ($map->links as $link) {
            if ($link->src_node_id && $link->port_id_a) {
                $portsByNode[$link->src_node_id][] = (int) $link->port_id_a;
            }
            if ($link->dst_node_id && $link->port_id_b) {
                $portsByNode[$link->dst_node_id][] = (int) $link->port_id_b;
            }
        }
        return $portsByNode;
    }

    private function buildNodeStatusData(Map $map, array $portsByNode, array $linkData, PortUtilService $svc): array
    {
        $nodeData = [];
        foreach ($map->nodes as $node) {
            $nodeData[$node->id] = $this->calculateNodeStatusAndTraffic(
                $node,
                $portsByNode[$node->id] ?? [],
                $linkData,
                $svc
            );
        }
        return $nodeData;
    }

    private function calculateNodeStatusAndTraffic($node, array $portIds, array $linkData, PortUtilService $svc): array
    {
        $status = $this->getNodeDeviceStatus($node);
        $trafficData = $this->aggregateNodeTraffic($node, $portIds, $linkData, $svc);

        return array_merge([
            'status' => $status,
            'device_id' => $node->device_id,
        ], $trafficData);
    }

    private function getNodeDeviceStatus($node): string
    {
        if (!$node->device_id) {
            return 'unknown';
        }

        try {
            if (class_exists('App\\Models\\Device')) {
                $device = \App\Models\Device::find($node->device_id);
                if ($device) {
                    return ($device->status ?? 0) ? 'up' : 'down';
                }
            } else {
                $row = \DB::table('devices')->select('status')->where('device_id', $node->device_id)->first();
                if ($row) {
                    return ($row->status ?? 0) ? 'up' : 'down';
                }
            }
        } catch (\Exception $e) {
        }

        return 'unknown';
    }

    private function aggregateNodeTraffic($node, array $portIds, array $linkData, PortUtilService $svc): array
    {
        $inboundSum = 0;
        $outboundSum = 0;
        $dataSource = 'none';

        // First try to aggregate from directly connected ports
        if (!empty($portIds)) {
            foreach (array_unique($portIds) as $portId) {
                try {
                    $portData = $svc->getPortData((int) $portId);
                    $inboundSum += (int) ($portData['in'] ?? 0);
                    $outboundSum += (int) ($portData['out'] ?? 0);
                } catch (\Throwable $e) {
                }
            }
            if (($inboundSum + $outboundSum) > 0) {
                $dataSource = 'ports';
            }
        }

        // Fallback: sum from link data if no direct port data
        if (($inboundSum + $outboundSum) === 0) {
            foreach ($linkData as $linkId => $linkInfo) {
                // Find links connected to this node
                $link = collect($node->map->links)->first(fn($lnk) => $lnk->id == $linkId);
                if ($link && ($link->src_node_id == $node->id || $link->dst_node_id == $node->id)) {
                    $inboundSum += (int) ($linkInfo['in_bps'] ?? 0);
                    $outboundSum += (int) ($linkInfo['out_bps'] ?? 0);
                }
            }
            if (($inboundSum + $outboundSum) > 0) {
                $dataSource = 'links';
            }
        }

        // Final fallback: sum top ports on the device
        if (($inboundSum + $outboundSum) === 0 && $node->device_id) {
            $agg = $svc->deviceAggregateBits((int) $node->device_id, 24);
            $inboundSum = (int) ($agg['in'] ?? 0);
            $outboundSum = (int) ($agg['out'] ?? 0);
            if (($inboundSum + $outboundSum) > 0) {
                $dataSource = 'device';
            }
        }

        // Heuristic: if device_id not set and we still have 0, try match device by node label
        if (($inboundSum + $outboundSum) === 0 && empty($node->device_id) && !empty($node->label)) {
            try {
                $row = \DB::table('devices')
                    ->select('device_id')
                    ->where('hostname', $node->label)
                    ->orWhere('sysName', $node->label)
                    ->first();
                if ($row && isset($row->device_id)) {
                    $agg = $svc->deviceAggregateBits((int) $row->device_id, 24);
                    $inboundSum = (int) ($agg['in'] ?? 0);
                    $outboundSum = (int) ($agg['out'] ?? 0);
                    if (($inboundSum + $outboundSum) > 0) {
                        $dataSource = 'device_guess';
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return [
            'traffic' => [
                'in_bps' => $inboundSum,
                'out_bps' => $outboundSum,
                'sum_bps' => $inboundSum + $outboundSum,
                'source' => $dataSource,
            ],
        ];
    }

    private function buildCompleteAlertData(Map $map, array $deviceAlerts, AlertService $alerts): array
    {
        $alertData = [
            'nodes' => [],
            'links' => [],
        ];

        // Add device alerts to nodes
        foreach ($map->nodes as $node) {
            if ($node->device_id && isset($deviceAlerts[(int)$node->device_id])) {
                $alertData['nodes'][$node->id] = $deviceAlerts[(int)$node->device_id];
            }
        }

        // Add link alerts based on port alerts
        $alertData['links'] = $this->buildLinkAlertData($map, $alerts);

        return $alertData;
    }

    private function buildLinkAlertData(Map $map, AlertService $alerts): array
    {
        $linkAlerts = [];

        $portIds = [];
        foreach ($map->links as $link) {
            if ($link->port_id_a) {
                $portIds[] = (int) $link->port_id_a;
            }
            if ($link->port_id_b) {
                $portIds[] = (int) $link->port_id_b;
            }
        }
        $portIds = array_values(array_unique($portIds));
        $portAlerts = $alerts->portAlerts($portIds);

        foreach ($map->links as $link) {
            $alertCount = 0;
            $maxSeverity = null;

            foreach ([(int)$link->port_id_a, (int)$link->port_id_b] as $portId) {
                if ($portId && isset($portAlerts[$portId])) {
                    $alertCount += $portAlerts[$portId]['count'];
                    $maxSeverity = $maxSeverity
                        ? $this->maxSeverity($maxSeverity, $portAlerts[$portId]['severity'])
                        : $portAlerts[$portId]['severity'];
                }
            }

            if ($alertCount > 0) {
                $linkAlerts[$link->id] = [
                    'count' => $alertCount,
                    'severity' => $maxSeverity ?? 'warning'
                ];
            }
        }

        return $linkAlerts;
    }

    public function embed(Map $map)
    {
        $mapData = $map->toJsonModel();
        $mapId = $map->id;
        return view('WeathermapNG::embed', compact('mapData', 'mapId'));
    }

    public function export(Map $map, Request $request)
    {
        $format = $request->get('format', 'json');

        if ($format === 'json') {
            return response()->json($map->toJsonModel())
                           ->header('Content-Disposition', 'attachment; filename="' . $map->name . '.json"');
        }

        // Could add other export formats here (XML, YAML, etc.)
        return response()->json(['error' => 'Unsupported format'], 400);
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:json|max:10240', // 10MB max
            'name' => 'required|string|max:255|unique:wmng_maps,name',
            'title' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true);

        if (!$data || !isset($data['nodes']) || !isset($data['links'])) {
            return response()->json(['error' => 'Invalid map file format'], 400);
        }

        // Create the map
        $map = Map::create([
            'name' => $validated['name'],
            'title' => $validated['title'] ?? $validated['name'],
            'options' => $data['options'] ?? [],
        ]);

        // Import nodes and build old->new id map if provided
        $nodeIdMap = [];
        foreach ($data['nodes'] as $nodeData) {
            $new = Node::create([
                'map_id' => $map->id,
                'label' => $nodeData['label'] ?? ('node-' . uniqid()),
                'x' => $nodeData['x'] ?? 0,
                'y' => $nodeData['y'] ?? 0,
                'device_id' => $nodeData['device_id'] ?? null,
                'meta' => $nodeData['meta'] ?? [],
            ]);
            $oldId = $nodeData['id'] ?? $nodeData['node_id'] ?? null;
            if ($oldId !== null) {
                $nodeIdMap[$oldId] = $new->id;
            }
        }

        // Import links with node id mapping
        if (!empty($data['links'])) {
            foreach ($data['links'] as $linkData) {
                $oldSrc = $linkData['src'] ?? $linkData['source'] ?? $linkData['src_node_id'] ?? null;
                $oldDst = $linkData['dst'] ?? $linkData['target'] ?? $linkData['dst_node_id'] ?? null;
                $srcId = $nodeIdMap[$oldSrc] ?? $oldSrc; // if old ids missing, try as-is
                $dstId = $nodeIdMap[$oldDst] ?? $oldDst;
                if (!$srcId || !$dstId) {
                    continue; // skip malformed
                }
                Link::create([
                    'map_id' => $map->id,
                    'src_node_id' => $srcId,
                    'dst_node_id' => $dstId,
                    'port_id_a' => $linkData['port_id_a'] ?? null,
                    'port_id_b' => $linkData['port_id_b'] ?? null,
                    'bandwidth_bps' => $linkData['bandwidth_bps'] ?? null,
                    'style' => $linkData['style'] ?? [],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'map_id' => $map->id,
            'message' => 'Map imported successfully'
        ]);
    }

    /**
     * Server-Sent Events stream for live map updates
     * GET /plugin/WeathermapNG/api/maps/{map}/sse
     */
    public function sse(Map $map, PortUtilService $svc, Request $request, AlertService $alerts)
    {
        $interval = max(1, (int) $request->get('interval', 5));
        $maxSeconds = (int) $request->get('max', 60); // connection duration

        return response()->stream(function () use ($map, $svc, $interval, $maxSeconds) {
            $start = time();
            // Disable PHP output buffering
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            @ini_set('zlib.output_compression', 0);
            @ini_set('implicit_flush', 1);
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @ob_implicit_flush(1);

            while (true) {
                $payload = [
                    'ts' => time(),
                    'links' => [],
                    'nodes' => [],
                    'alerts' => [ 'nodes' => [], 'links' => [] ],
                ];

                // Links utilization
                foreach ($map->links as $link) {
                    $payload['links'][$link->id] = $svc->linkUtilBits([
                        'port_id_a' => $link->port_id_a,
                        'port_id_b' => $link->port_id_b,
                        'bandwidth_bps' => $link->bandwidth_bps,
                    ]);
                }

                // Node statuses and metrics
                $deviceIds = [];
                foreach ($map->nodes as $node) {
                    if ($node->device_id) {
                        $deviceIds[] = (int) $node->device_id;
                    }
                }
                $deviceIds = array_values(array_unique($deviceIds));
                $devAlerts = $alerts->deviceAlerts($deviceIds);
                // Build per-node port lists from links
                $portsByNode = [];
                foreach ($map->links as $lnk) {
                    if ($lnk->src_node_id && $lnk->port_id_a) {
                        $portsByNode[$lnk->src_node_id] = $portsByNode[$lnk->src_node_id] ?? [];
                        $portsByNode[$lnk->src_node_id][] = (int) $lnk->port_id_a;
                    }
                    if ($lnk->dst_node_id && $lnk->port_id_b) {
                        $portsByNode[$lnk->dst_node_id] = $portsByNode[$lnk->dst_node_id] ?? [];
                        $portsByNode[$lnk->dst_node_id][] = (int) $lnk->port_id_b;
                    }
                }
                foreach ($map->nodes as $node) {
                    $status = 'unknown';
                    $metrics = ['cpu' => null, 'mem' => null];
                    if ($node->device_id) {
                        try {
                            if (class_exists('App\\Models\\Device')) {
                                $dev = \App\Models\Device::find($node->device_id);
                                if ($dev) {
                                    $status = ($dev->status ?? 0) ? 'up' : 'down';
                                }
                            } else {
                                $row = \DB::table('devices')->select('status')->where('device_id', $node->device_id)->first();
                                if ($row) {
                                    $status = ($row->status ?? 0) ? 'up' : 'down';
                                }
                            }
                            // Best-effort metrics from DB
                            try {
                                $cpu = \DB::table('processors')->where('device_id', $node->device_id)->avg('processor_usage');
                                if ($cpu !== null) {
                                    $metrics['cpu'] = round((float) $cpu, 2);
                                }
                            } catch (\Exception $e) {
                            }
                            try {
                                $mem = \DB::table('mempools')->where('device_id', $node->device_id)->avg('mempool_perc');
                                if ($mem !== null) {
                                    $metrics['mem'] = round((float) $mem, 2);
                                }
                            } catch (\Exception $e) {
                            }
                        } catch (\Exception $e) {
                            $status = 'unknown';
                        }
                    }
                    // Aggregate node traffic from connected ports
                    $inSum = 0;
                    $outSum = 0;
                    $source = 'none';
                    if (!empty($portsByNode[$node->id])) {
                        foreach (array_unique($portsByNode[$node->id]) as $pid) {
                            try {
                                $pd = $svc->getPortData((int) $pid);
                                $inSum += (int) ($pd['in'] ?? 0);
                                $outSum += (int) ($pd['out'] ?? 0);
                            } catch (\Throwable $e) {
                            }
                        }
                        if (($inSum + $outSum) > 0) {
                            $source = 'ports';
                        }
                    }
                    // Fallback to link sums when no port data
                    if (($inSum + $outSum) === 0) {
                        foreach ($map->links as $lnk) {
                            if ($lnk->src_node_id == $node->id || $lnk->dst_node_id == $node->id) {
                                $ld = $payload['links'][$lnk->id] ?? null;
                                if (is_array($ld)) {
                                    $inSum += (int) ($ld['in_bps'] ?? 0);
                                    $outSum += (int) ($ld['out_bps'] ?? 0);
                                }
                            }
                        }
                        if (($inSum + $outSum) > 0) {
                            $source = 'links';
                        }
                    }
                    // Final device-level aggregate
                    if (($inSum + $outSum) === 0 && $node->device_id) {
                        $agg = $svc->deviceAggregateBits((int) $node->device_id, 24);
                        $inSum = (int) ($agg['in'] ?? 0);
                        $outSum = (int) ($agg['out'] ?? 0);
                        if (($inSum + $outSum) > 0) {
                            $source = 'device';
                        }
                    }
                    // Heuristic: match device by node label if no device_id
                    if (($inSum + $outSum) === 0 && empty($node->device_id) && !empty($node->label)) {
                        try {
                            $row = DB::table('devices')
                                ->select('device_id')
                                ->where('hostname', $node->label)
                                ->orWhere('sysName', $node->label)
                                ->first();
                            if ($row && isset($row->device_id)) {
                                $agg = $svc->deviceAggregateBits((int) $row->device_id, 24);
                                $inSum = (int) ($agg['in'] ?? 0);
                                $outSum = (int) ($agg['out'] ?? 0);
                                if (($inSum + $outSum) > 0) {
                                    $source = 'device_guess';
                                }
                            }
                        } catch (\Throwable $e) {
                        }
                    }
                    $payload['nodes'][$node->id] = [
                        'status' => $status,
                        'metrics' => $metrics,
                        'traffic' => [
                            'in_bps' => $inSum,
                            'out_bps' => $outSum,
                            'sum_bps' => $inSum + $outSum,
                            'source' => $source,
                        ],
                    ];
                    if ($node->device_id && isset($devAlerts[(int)$node->device_id])) {
                        $payload['alerts']['nodes'][$node->id] = $devAlerts[(int)$node->device_id];
                    }
                }

                // Port/Link alerts
                $portIds = [];
                foreach ($map->links as $lnk) {
                    if ($lnk->port_id_a) {
                        $portIds[] = (int) $lnk->port_id_a;
                    }
                    if ($lnk->port_id_b) {
                        $portIds[] = (int) $lnk->port_id_b;
                    }
                }
                $portIds = array_values(array_unique($portIds));
                $portAlerts = $alerts->portAlerts($portIds);
                foreach ($map->links as $lnk) {
                    $count = 0;
                    $sev = null;
                    foreach ([(int)$lnk->port_id_a, (int)$lnk->port_id_b] as $pid) {
                        if ($pid && isset($portAlerts[$pid])) {
                            $count += $portAlerts[$pid]['count'];
                            $sev = $sev ? $this->maxSeverity($sev, $portAlerts[$pid]['severity']) : $portAlerts[$pid]['severity'];
                        }
                    }
                    if ($count > 0) {
                        $payload['alerts']['links'][$lnk->id] = ['count' => $count, 'severity' => $sev ?? 'warning'];
                    }
                }

                // Emit SSE message
                echo 'data: ' . json_encode($payload) . "\n\n";
                @ob_flush();
                @flush();

                if (connection_aborted() || (time() - $start) >= $maxSeconds) {
                    break;
                }

                sleep($interval);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    private function maxSeverity(string $a, string $b): string
    {
        $w = ['ok' => 0, 'warning' => 1, 'critical' => 2, 'severe' => 3];
        $wa = $w[$a] ?? 0;
        $wb = $w[$b] ?? 0;
        return $wa >= $wb ? $a : $b;
    }
}
