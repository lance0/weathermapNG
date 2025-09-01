<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use Illuminate\Http\Request;

class MapController
{
    public function index()
    {
        $maps = Map::withCount(['nodes', 'links'])->get();
        return view('WeathermapNG::index', compact('maps'));
    }

    public function show(Map $map)
    {
        // Redirect to embed view to avoid missing template issues
        return redirect()->route('weathermapng.embed', $map);
    }

    public function editor(Map $map)
    {
        $map->load(['nodes', 'links']);

        // Get devices for the editor
        $devices = $this->getDevicesForEditor();

        return view('WeathermapNG::editor', compact('map', 'devices'));
    }

    public function editorD3(Map $map)
    {
        $map->load(['nodes', 'links']);
        $title = 'WeathermapNG - D3 Editor';
        return view('WeathermapNG::editor-d3', compact('map', 'title'));
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:wmng_maps,name',
            'title' => 'nullable|string|max:255',
            'width' => 'nullable|integer|min:100|max:4096',
            'height' => 'nullable|integer|min:100|max:4096',
        ]);

        $options = [
            'width' => $validated['width'] ?? 800,
            'height' => $validated['height'] ?? 600,
            'background' => '#ffffff',
        ];

        $map = Map::create([
            'name' => $validated['name'],
            'title' => $validated['title'] ?? $validated['name'],
            'options' => $options,
        ]);

        // Return JSON for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Map created successfully!',
                'map' => $map,
                'redirect' => route('weathermapng.editor', $map)
            ]);
        }

        return redirect()->route('weathermapng.editor', $map)
                        ->with('success', 'Map created successfully!');
    }

    public function update(Request $request, Map $map)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'width' => 'nullable|integer|min:100|max:4096',
            'height' => 'nullable|integer|min:100|max:4096',
            'background' => 'nullable|string',
        ]);

        $options = $map->options ?? [];
        $options['width'] = $validated['width'] ?? $options['width'] ?? 800;
        $options['height'] = $validated['height'] ?? $options['height'] ?? 600;
        $options['background'] = $validated['background'] ?? $options['background'] ?? '#ffffff';

        $map->update([
            'title' => $validated['title'] ?? $map->title,
            'options' => $options,
        ]);

        return response()->json(['success' => true, 'map' => $map]);
    }

    public function destroy(Map $map)
    {
        $map->delete();

        // Return JSON for AJAX requests
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Map deleted successfully!'
            ]);
        }

        return redirect()->route('weathermapng.index')
                        ->with('success', 'Map deleted successfully!');
    }

    public function storeNodes(Request $request, Map $map)
    {
        $validated = $request->validate([
            'nodes' => 'required|array',
            'nodes.*.label' => 'required|string|max:255',
            'nodes.*.x' => 'required|numeric',
            'nodes.*.y' => 'required|numeric',
            'nodes.*.device_id' => 'nullable|integer',
        ]);

        // Delete existing nodes
        $map->nodes()->delete();

        // Create new nodes
        foreach ($validated['nodes'] as $nodeData) {
            Node::create([
                'map_id' => $map->id,
                'label' => $nodeData['label'],
                'x' => $nodeData['x'],
                'y' => $nodeData['y'],
                'device_id' => $nodeData['device_id'] ?? null,
                'meta' => $nodeData['meta'] ?? [],
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function storeLinks(Request $request, Map $map)
    {
        $validated = $request->validate([
            'links' => 'required|array',
            'links.*.src_node_id' => 'required|integer|exists:wmng_nodes,id',
            'links.*.dst_node_id' => 'required|integer|exists:wmng_nodes,id',
            'links.*.port_id_a' => 'nullable|integer',
            'links.*.port_id_b' => 'nullable|integer',
            'links.*.bandwidth_bps' => 'nullable|integer',
        ]);

        // Delete existing links
        $map->links()->delete();

        // Create new links
        foreach ($validated['links'] as $linkData) {
            Link::create([
                'map_id' => $map->id,
                'src_node_id' => $linkData['src_node_id'],
                'dst_node_id' => $linkData['dst_node_id'],
                'port_id_a' => $linkData['port_id_a'] ?? null,
                'port_id_b' => $linkData['port_id_b'] ?? null,
                'bandwidth_bps' => $linkData['bandwidth_bps'] ?? null,
                'style' => $linkData['style'] ?? [],
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Create a single node immediately
     */
    public function createNode(Request $request, Map $map)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'x' => 'required|numeric',
            'y' => 'required|numeric',
            'device_id' => 'nullable|integer',
            'meta' => 'array',
        ]);

        $node = Node::create([
            'map_id' => $map->id,
            'label' => $data['label'],
            'x' => $data['x'],
            'y' => $data['y'],
            'device_id' => $data['device_id'] ?? null,
            'meta' => $data['meta'] ?? [],
        ]);

        return response()->json(['success' => true, 'node' => $node]);
    }

    /**
     * Delete a single node
     */
    public function deleteNode(Map $map, Node $node)
    {
        if ($node->map_id !== $map->id) {
            return response()->json(['success' => false, 'message' => 'Node does not belong to map'], 400);
        }
        // cascade delete links attached to this node
        $map->links()->where('src_node_id', $node->id)->orWhere('dst_node_id', $node->id)->delete();
        $node->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Create a single link immediately
     */
    public function createLink(Request $request, Map $map)
    {
        $data = $request->validate([
            'src_node_id' => 'required|integer|exists:wmng_nodes,id',
            'dst_node_id' => 'required|integer|exists:wmng_nodes,id',
            'port_id_a' => 'nullable|integer',
            'port_id_b' => 'nullable|integer',
            'bandwidth_bps' => 'nullable|integer',
            'style' => 'array',
        ]);

        // Validate port-device pairing if ports are provided
        if (($data['port_id_a'] ?? null) || ($data['port_id_b'] ?? null)) {
            $src = Node::find($data['src_node_id']);
            $dst = Node::find($data['dst_node_id']);
            if (!$src || !$dst || $src->map_id !== $map->id || $dst->map_id !== $map->id) {
                return response()->json(['success' => false, 'message' => 'Invalid node(s)'], 422);
            }
            try {
                if (($data['port_id_a'] ?? null) && class_exists('\\App\\Models\\Port')) {
                    $pa = \App\Models\Port::find($data['port_id_a']);
                    if (!$pa || ($src->device_id && $pa->device_id != $src->device_id)) {
                        return response()->json(['success' => false, 'message' => 'Source port does not belong to source device'], 422);
                    }
                }
                if (($data['port_id_b'] ?? null) && class_exists('\\App\\Models\\Port')) {
                    $pb = \App\Models\Port::find($data['port_id_b']);
                    if (!$pb || ($dst->device_id && $pb->device_id != $dst->device_id)) {
                        return response()->json(['success' => false, 'message' => 'Destination port does not belong to destination device'], 422);
                    }
                }
            } catch (\Exception $e) {
                // If we cannot validate via models, skip strict check
            }
        }

        $link = Link::create([
            'map_id' => $map->id,
            'src_node_id' => $data['src_node_id'],
            'dst_node_id' => $data['dst_node_id'],
            'port_id_a' => $data['port_id_a'] ?? null,
            'port_id_b' => $data['port_id_b'] ?? null,
            'bandwidth_bps' => $data['bandwidth_bps'] ?? null,
            'style' => $data['style'] ?? [],
        ]);

        return response()->json(['success' => true, 'link' => $link]);
    }

    /**
     * Delete a single link
     */
    public function deleteLink(Map $map, Link $link)
    {
        if ($link->map_id !== $map->id) {
            return response()->json(['success' => false, 'message' => 'Link does not belong to map'], 400);
        }
        $link->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Combined save endpoint to persist map options, nodes, and links
     * POST /plugin/WeathermapNG/api/maps/{map}/save
     */
    public function save(Request $request, Map $map)
    {
        try {
            $data = $request->validate([
                'title' => 'nullable|string|max:255',
                'options' => 'array',
                'options.width' => 'nullable|integer|min:100|max:4096',
                'options.height' => 'nullable|integer|min:100|max:4096',
                'options.background' => 'nullable|string',
                'nodes' => 'array',
                'nodes.*.label' => 'required|string|max:255',
                'nodes.*.x' => 'required|numeric',
                'nodes.*.y' => 'required|numeric',
                'nodes.*.device_id' => 'nullable|integer',
                'nodes.*.meta' => 'array',
                'links' => 'array', // accept loose link shapes; coerce below
            ]);

            \DB::transaction(function () use ($map, $data) {
                // Update map options/title
                if (!empty($data['options']) || !empty($data['title'])) {
                    $opts = $map->options ?? [];
                    $opts['width'] = $data['options']['width'] ?? ($opts['width'] ?? 800);
                    $opts['height'] = $data['options']['height'] ?? ($opts['height'] ?? 600);
                    if (isset($data['options']['background'])) {
                        $opts['background'] = $data['options']['background'];
                    }
                    $map->title = $data['title'] ?? $map->title;
                    $map->options = $opts;
                    $map->save();
                }

                // Delete links first, then nodes (FK safety)
                $map->links()->delete();
                $map->nodes()->delete();

                // Create nodes and build id mapping (client -> new id)
                $nodeIdMap = [];
                if (!empty($data['nodes'])) {
                    foreach ($data['nodes'] as $idx => $n) {
                        $new = Node::create([
                            'map_id' => $map->id,
                            'label' => $n['label'],
                            'x' => $n['x'],
                            'y' => $n['y'],
                            'device_id' => $n['device_id'] ?? null,
                            'meta' => $n['meta'] ?? [],
                        ]);
                        $clientKey = $n['id'] ?? $n['node_id'] ?? $n['_id'] ?? (string)$idx;
                        $nodeIdMap[(string)$clientKey] = $new->id;
                    }
                }

                // Create links using id mapping
                if (!empty($data['links'])) {
                    foreach ($data['links'] as $l) {
                        $srcKey = $l['src_node_id'] ?? $l['src'] ?? $l['source'] ?? null;
                        $dstKey = $l['dst_node_id'] ?? $l['dst'] ?? $l['target'] ?? null;
                        // Handle D3 objects {source: {id}, target: {id}}
                        if (is_array($srcKey)) { $srcKey = $srcKey['id'] ?? $srcKey['node_id'] ?? null; }
                        if (is_array($dstKey)) { $dstKey = $dstKey['id'] ?? $dstKey['node_id'] ?? null; }
                        $srcId = $nodeIdMap[(string)$srcKey] ?? (is_numeric($srcKey) ? (int)$srcKey : null);
                        $dstId = $nodeIdMap[(string)$dstKey] ?? (is_numeric($dstKey) ? (int)$dstKey : null);
                        if (!$srcId || !$dstId) {
                            // skip invalid links rather than failing the whole save
                            continue;
                        }
                        Link::create([
                            'map_id' => $map->id,
                            'src_node_id' => $srcId,
                            'dst_node_id' => $dstId,
                            'port_id_a' => $l['port_id_a'] ?? null,
                            'port_id_b' => $l['port_id_b'] ?? null,
                            'bandwidth_bps' => $l['bandwidth_bps'] ?? $l['bandwidth'] ?? null,
                            'style' => $l['style'] ?? [],
                        ]);
                    }
                }
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save map: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Patch a single node
     */
    public function updateNode(Request $request, Map $map, Node $node)
    {
        if ($node->map_id !== $map->id) {
            return response()->json(['success' => false, 'message' => 'Node does not belong to map'], 400);
        }

        $data = $request->validate([
            'label' => 'sometimes|string|max:255',
            'x' => 'sometimes|numeric',
            'y' => 'sometimes|numeric',
            'device_id' => 'sometimes|nullable|integer',
            'meta' => 'sometimes|array',
        ]);

        $node->fill($data);
        $node->save();

        return response()->json(['success' => true, 'node' => $node]);
    }

    /**
     * Patch a single link
     */
    public function updateLink(Request $request, Map $map, Link $link)
    {
        if ($link->map_id !== $map->id) {
            return response()->json(['success' => false, 'message' => 'Link does not belong to map'], 400);
        }

        $data = $request->validate([
            'src_node_id' => 'sometimes|integer',
            'dst_node_id' => 'sometimes|integer',
            'port_id_a' => 'sometimes|nullable|integer',
            'port_id_b' => 'sometimes|nullable|integer',
            'bandwidth_bps' => 'sometimes|nullable|integer',
            'style' => 'sometimes|array',
        ]);

        $link->fill($data);
        $link->save();

        return response()->json(['success' => true, 'link' => $link]);
    }

    private function getDevicesForEditor()
    {
        try {
            if (class_exists('\App\Models\Device')) {
                return \App\Models\Device::select('device_id', 'hostname', 'sysName')
                                        ->where('disabled', 0)
                                        ->where('ignore', 0)
                                        ->orderBy('hostname')
                                        ->get();
            }

            // Fallback for older versions
            $devices = dbFetchRows(
                "SELECT device_id, hostname, sysName\n" .
                "FROM devices\n" .
                "WHERE disabled = 0 AND ignore = 0\n" .
                "ORDER BY hostname"
            );
            return collect($devices)->map(function ($device) {
                return (object) $device;
            });
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Auto-discover nodes and links from LibreNMS topology and seed into this map.
     * Best-effort: uses 'links' table if present to connect ports; falls back to neighbours.
     */
    public function autoDiscover(Request $request, Map $map)
    {
        try {
            $minDegree = max(0, (int) $request->input('min_degree', 0));
            $osFilter = trim((string) $request->input('os', ''));
            $osParts = array_filter(array_map('trim', explode(',', $osFilter)));
            // Collect devices
            $devices = [];
            if (class_exists('\\App\\Models\\Device')) {
                $q = \App\Models\Device::where('disabled', 0)->where('ignore', 0)->select('device_id', 'hostname', 'os');
                if (!empty($osParts)) {
                    $q->where(function($qb) use ($osParts) {
                        foreach ($osParts as $i => $part) {
                            $method = $i === 0 ? 'where' : 'orWhere';
                            $qb->$method('os', 'like', '%' . $part . '%');
                        }
                    });
                }
                $devices = $q->get()->toArray();
            } else {
                $q = \DB::table('devices')->where('disabled', 0)->where('ignore', 0)->select('device_id', 'hostname', 'os');
                if (!empty($osParts)) {
                    $q->where(function($qb) use ($osParts) {
                        foreach ($osParts as $i => $part) {
                            $method = $i === 0 ? 'where' : 'orWhere';
                            $qb->$method('os', 'like', '%' . $part . '%');
                        }
                    });
                }
                $devices = $q->get()->toArray();
                $devices = array_map(fn($o) => (array)$o, $devices);
            }
            // Map existing nodes by device_id to avoid duplicates
            $existing = $map->nodes()->pluck('id', 'device_id')->filter()->toArray();
            $nodeIdsByDevice = $existing;

            // Create missing nodes with a temporary rough grid layout (will be repositioned below)
            $x = 100; $y = 100; $step = 120; $cols = 8; $i = 0;
            foreach ($devices as $dev) {
                $did = (int)($dev['device_id'] ?? 0);
                if (!$did) continue;
                if ($minDegree > 0) { $dval = $deg[$did] ?? 0; if ($dval < $minDegree) continue; }
                if (!isset($nodeIdsByDevice[$did])) {
                    $node = Node::create([
                        'map_id' => $map->id,
                        'label' => $dev['hostname'] ?? ('dev-' . $did),
                        'x' => $x,
                        'y' => $y,
                        'device_id' => $did,
                        'meta' => ['icon' => 'switch'],
                    ]);
                    $nodeIdsByDevice[$did] = $node->id;
                    $i++; $x += $step; if ($i % $cols === 0) { $x = 100; $y += $step; }
                }
            }

            // Discover links using 'links' table if present
            $linksInserted = 0;
            try {
                $links = \DB::table('links')->select('local_port_id','remote_port_id')->limit(2000)->get();
                foreach ($links as $lnk) {
                    $pa = (int)$lnk->local_port_id; $pb = (int)$lnk->remote_port_id;
                    if (!$pa || !$pb) continue;
                    // lookup ports to get device ids
                    $rowA = \DB::table('ports')->select('device_id')->where('port_id', $pa)->first();
                    $rowB = \DB::table('ports')->select('device_id')->where('port_id', $pb)->first();
                    if (!$rowA || !$rowB) continue;
                    $da = (int)$rowA->device_id; $db = (int)$rowB->device_id;
                    $srcNodeId = $nodeIdsByDevice[$da] ?? null;
                    $dstNodeId = $nodeIdsByDevice[$db] ?? null;
                    if (!$srcNodeId || !$dstNodeId) continue;
                    // avoid duplicates by checking existing link
                    $exists = $map->links()->where('src_node_id', $srcNodeId)->where('dst_node_id', $dstNodeId)->exists();
                    if ($exists) continue;
                    Link::create([
                        'map_id' => $map->id,
                        'src_node_id' => $srcNodeId,
                        'dst_node_id' => $dstNodeId,
                        'port_id_a' => $pa,
                        'port_id_b' => $pb,
                        'bandwidth_bps' => null,
                        'style' => [],
                    ]);
                    $linksInserted++;
                }
            } catch (\Exception $e) {
                // Fallback to neighbours table
                try {
                    $neigh = \DB::table('neighbours')->select('port_id','remote_port_id')->limit(2000)->get();
                    foreach ($neigh as $n) {
                        $pa = (int)$n->port_id; $pb = (int)$n->remote_port_id;
                        if (!$pa || !$pb) continue;
                        $rowA = \DB::table('ports')->select('device_id')->where('port_id', $pa)->first();
                        $rowB = \DB::table('ports')->select('device_id')->where('port_id', $pb)->first();
                        if (!$rowA || !$rowB) continue;
                        $da = (int)$rowA->device_id; $db = (int)$rowB->device_id;
                        $srcNodeId = $nodeIdsByDevice[$da] ?? null;
                        $dstNodeId = $nodeIdsByDevice[$db] ?? null;
                        if (!$srcNodeId || !$dstNodeId) continue;
                        $exists = $map->links()->where('src_node_id', $srcNodeId)->where('dst_node_id', $dstNodeId)->exists();
                        if ($exists) continue;
                        Link::create([
                            'map_id' => $map->id,
                            'src_node_id' => $srcNodeId,
                            'dst_node_id' => $dstNodeId,
                            'port_id_a' => $pa,
                            'port_id_b' => $pb,
                            'bandwidth_bps' => null,
                            'style' => [],
                        ]);
                        $linksInserted++;
                    }
                } catch (\Exception $e2) {
                    // no topology available
                }
            }

            // Compute device degrees to inform layout (core vs edge)
            $deg = [];
            try {
                // Prefer links table
                $rows = \DB::table('links')->select('local_port_id','remote_port_id')->limit(5000)->get();
                foreach ($rows as $r) {
                    $pa = (int)$r->local_port_id; $pb = (int)$r->remote_port_id;
                    if (!$pa || !$pb) continue;
                    $ra = \DB::table('ports')->select('device_id')->where('port_id', $pa)->first();
                    $rb = \DB::table('ports')->select('device_id')->where('port_id', $pb)->first();
                    if ($ra) { $d = (int)$ra->device_id; $deg[$d] = ($deg[$d] ?? 0) + 1; }
                    if ($rb) { $d = (int)$rb->device_id; $deg[$d] = ($deg[$d] ?? 0) + 1; }
                }
            } catch (\Exception $e) {
                try {
                    $rows = \DB::table('neighbours')->select('port_id','remote_port_id')->limit(5000)->get();
                    foreach ($rows as $r) {
                        $pa = (int)$r->port_id; $pb = (int)$r->remote_port_id;
                        if (!$pa || !$pb) continue;
                        $ra = \DB::table('ports')->select('device_id')->where('port_id', $pa)->first();
                        $rb = \DB::table('ports')->select('device_id')->where('port_id', $pb)->first();
                        if ($ra) { $d = (int)$ra->device_id; $deg[$d] = ($deg[$d] ?? 0) + 1; }
                        if ($rb) { $d = (int)$rb->device_id; $deg[$d] = ($deg[$d] ?? 0) + 1; }
                    }
                } catch (\Exception $e2) {}
            }

            // Layout: center high-degree devices, then rings for the rest
            $ids = array_keys($nodeIdsByDevice);
            usort($ids, function($a,$b) use ($deg) { return ($deg[$b] ?? 0) <=> ($deg[$a] ?? 0); });
            $total = count($ids);
            if ($total > 0) {
                $centerX = 800; $centerY = 600; // general center; editor may rescale
                $ring1 = max(4, (int)ceil($total * 0.15));
                $ring2 = max(6, (int)ceil($total * 0.35));
                $radii = [180, 320, 480];
                $batches = [array_slice($ids, 0, $ring1), array_slice($ids, $ring1, $ring2), array_slice($ids, $ring1 + $ring2)];
                foreach ($batches as $ri => $batch) {
                    $n = max(1, count($batch));
                    $angleStep = 2 * M_PI / $n;
                    foreach ($batch as $k => $devId) {
                        $nodeId = $nodeIdsByDevice[$devId] ?? null;
                        if (!$nodeId) continue;
                        $angle = $k * $angleStep;
                        $x = (int)round($centerX + $radii[$ri] * cos($angle));
                        $y = (int)round($centerY + $radii[$ri] * sin($angle));
                        Node::where('id', $nodeId)->update(['x' => $x, 'y' => $y]);
                    }
                }
            }

            return response()->json(['success' => true, 'nodes' => count($nodeIdsByDevice), 'links' => $linksInserted, 'layout' => 'rings']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Auto-discovery failed: ' . $e->getMessage()], 500);
        }
    }
}
