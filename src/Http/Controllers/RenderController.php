<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;
use Illuminate\Http\Request;

class RenderController
{
    public function json(Map $map)
    {
        return response()->json($map->toJsonModel());
    }

    public function live(Map $map, PortUtilService $svc)
    {
        $out = [
            'ts' => time(),
            'links' => []
        ];

        foreach ($map->links as $link) {
            $out['links'][$link->id] = $svc->linkUtilBits([
                'port_id_a' => $link->port_id_a,
                'port_id_b' => $link->port_id_b,
                'bandwidth_bps' => $link->bandwidth_bps,
            ]);
        }

        return response()->json($out);
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
    public function sse(Map $map, PortUtilService $svc, Request $request)
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
                                if ($cpu !== null) { $metrics['cpu'] = round((float) $cpu, 2); }
                            } catch (\Exception $e) {}
                            try {
                                $mem = \DB::table('mempools')->where('device_id', $node->device_id)->avg('mempool_perc');
                                if ($mem !== null) { $metrics['mem'] = round((float) $mem, 2); }
                            } catch (\Exception $e) {}
                        } catch (\Exception $e) {
                            $status = 'unknown';
                        }
                    }
                    $payload['nodes'][$node->id] = [
                        'status' => $status,
                        'metrics' => $metrics,
                    ];
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
}
