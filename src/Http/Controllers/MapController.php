<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\Services\GridLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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

        $update = ['options' => $options];
        if (array_key_exists('title', $validated) && Schema::hasColumn('wmng_maps', 'title')) {
            $update['title'] = $validated['title'] ?? $map->title;
        }
        $map->update($update);

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
                    $sourcePort = \App\Models\Port::find($data['port_id_a']);
                    if (!$sourcePort || ($src->device_id && $sourcePort->device_id != $src->device_id)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Source port does not belong to source device'
                        ], 422);
                    }
                }
                if (($data['port_id_b'] ?? null) && class_exists('\\App\\Models\\Port')) {
                    $destinationPort = \App\Models\Port::find($data['port_id_b']);
                    if (!$destinationPort || ($dst->device_id && $destinationPort->device_id != $dst->device_id)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Destination port does not belong to destination device'
                        ], 422);
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
            $validatedData = $this->validateSaveRequest($request);

            \DB::transaction(function () use ($map, $validatedData) {
                $this->updateMapProperties($map, $validatedData);
                $this->replaceMapContent($map, $validatedData);
            });

            return response()->json(['success' => true, 'message' => 'Map saved successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save map: ' . $e->getMessage()
            ], 500);
        }
    }

    private function validateSaveRequest(Request $request): array
    {
        return $request->validate([
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
            'links' => 'array',
        ]);
    }

    private function updateMapProperties(Map $map, array $data): void
    {
        if (!empty($data['options']) || array_key_exists('title', $data)) {
            $updates = [];

            if (!empty($data['options'])) {
                $updates['options'] = $this->mergeMapOptions($map->options ?? [], $data['options']);
            }

            if (array_key_exists('title', $data) && Schema::hasColumn('wmng_maps', 'title')) {
                $updates['title'] = $data['title'];
            }

            if (!empty($updates)) {
                $map->fill($updates)->save();
            }
        }
    }

    private function mergeMapOptions(array $currentOptions, array $newOptions): array
    {
        return array_merge($currentOptions, array_filter([
            'width' => $newOptions['width'] ?? $currentOptions['width'] ?? 800,
            'height' => $newOptions['height'] ?? $currentOptions['height'] ?? 600,
            'background' => $newOptions['background'] ?? $currentOptions['background'] ?? null,
        ], fn($value) => $value !== null));
    }

    private function replaceMapContent(Map $map, array $data): void
    {
        // Delete existing content (links first due to FK constraints)
        $map->links()->delete();
        $map->nodes()->delete();

        $nodeIdMap = $this->createNodes($map, $data['nodes'] ?? []);
        $this->createLinks($map, $data['links'] ?? [], $nodeIdMap);
    }

    private function createNodes(Map $map, array $nodesData): array
    {
        $nodeIdMap = [];

        foreach ($nodesData as $index => $nodeData) {
            $node = Node::create([
                'map_id' => $map->id,
                'label' => $nodeData['label'],
                'x' => $nodeData['x'],
                'y' => $nodeData['y'],
                'device_id' => $nodeData['device_id'] ?? null,
                'meta' => $nodeData['meta'] ?? [],
            ]);

            $clientKey = $nodeData['id'] ?? $nodeData['node_id'] ?? $nodeData['_id'] ?? (string)$index;
            $nodeIdMap[$clientKey] = $node->id;
        }

        return $nodeIdMap;
    }

    private function createLinks(Map $map, array $linksData, array $nodeIdMap): void
    {
        foreach ($linksData as $linkData) {
            $sourceId = $this->resolveNodeId($linkData['src_node_id'] ?? $linkData['source'] ?? null, $nodeIdMap);
            $targetId = $this->resolveNodeId($linkData['dst_node_id'] ?? $linkData['target'] ?? null, $nodeIdMap);

            if ($sourceId && $targetId) {
                Link::create([
                    'map_id' => $map->id,
                    'src_node_id' => $sourceId,
                    'dst_node_id' => $targetId,
                    'port_id_a' => $linkData['port_id_a'] ?? $linkData['port_a'] ?? null,
                    'port_id_b' => $linkData['port_id_b'] ?? $linkData['port_b'] ?? null,
                    'bandwidth_bps' => $linkData['bandwidth_bps'] ?? $linkData['bandwidth'] ?? null,
                    'style' => $linkData['style'] ?? [],
                ]);
            }
        }
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
            $params = $this->validateAutoDiscoverRequest($request);
            $devices = $this->discoverDevices($params);
            $existingNodes = $this->getExistingNodeMapping($map);

            $nodeMapping = $this->createMissingNodes($map, $devices, $existingNodes, $params['minDegree']);
            $connectivityData = $this->buildConnectivityGraph($nodeMapping);
            $this->createDiscoveredLinks($map, $connectivityData, $nodeMapping);
            $this->applyLayoutAlgorithm($map, $nodeMapping, $connectivityData['links']);

            return response()->json(['success' => true, 'message' => 'Auto-discovery completed']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auto-discovery failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function validateAutoDiscoverRequest(Request $request): array
    {
        return [
            'minDegree' => max(0, (int) $request->input('min_degree', 0)),
            'osFilter' => array_filter(array_map('trim', explode(',', trim((string) $request->input('os', ''))))),
        ];
    }

    private function discoverDevices(array $params): array
    {
        $query = $this->buildDeviceQuery($params['osFilter']);
        $devices = $query->get()->toArray();

        // Ensure consistent array format
        return array_map(fn($device) => (array) $device, $devices);
    }

    private function buildDeviceQuery(array $osFilters)
    {
        $baseQuery = class_exists('\\App\\Models\\Device')
            ? \App\Models\Device::where('disabled', 0)->where('ignore', 0)->select('device_id', 'hostname', 'os')
            : \DB::table('devices')->where('disabled', 0)->where('ignore', 0)->select('device_id', 'hostname', 'os');

        if (!empty($osFilters)) {
            $baseQuery->where(function ($query) use ($osFilters) {
                foreach ($osFilters as $index => $filter) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->$method('os', 'like', '%' . $filter . '%');
                }
            });
        }

        return $baseQuery;
    }

    private function getExistingNodeMapping(Map $map): array
    {
        return $map->nodes()->pluck('id', 'device_id')->filter()->toArray();
    }

    private function createMissingNodes(Map $map, array $devices, array $existingNodes, int $minDegree): array
    {
        $nodeMapping = $existingNodes;
        $deviceDegrees = $this->calculateDeviceDegrees($devices);

        $layout = new GridLayout(100, 100, 120, 8);

        foreach ($devices as $device) {
            $deviceId = (int) ($device['device_id'] ?? 0);
            if (!$deviceId || isset($nodeMapping[$deviceId])) {
                continue;
            }

            if ($minDegree > 0 && ($deviceDegrees[$deviceId] ?? 0) < $minDegree) {
                continue;
            }

            $position = $layout->getNextPosition();

            $node = Node::create([
                'map_id' => $map->id,
                'label' => $device['hostname'] ?? "Device {$deviceId}",
                'x' => $position['x'],
                'y' => $position['y'],
                'device_id' => $deviceId,
                'meta' => [],
            ]);

            $nodeMapping[$deviceId] = $node->id;
        }

        return $nodeMapping;
    }

    private function calculateDeviceDegrees(array $devices): array
    {
        $degrees = [];
        $deviceIds = array_column($devices, 'device_id');

        $portsQuery = class_exists('\\App\\Models\\Port')
            ? \App\Models\Port::whereIn('device_id', $deviceIds)
                ->where('ifOperStatus', 'up')
                ->where('ifAdminStatus', 'up')
            : \DB::table('ports')->whereIn('device_id', $deviceIds)
                ->where('ifOperStatus', 'up')
                ->where('ifAdminStatus', 'up');

        $ports = $portsQuery->select('device_id')->get()->toArray();
        $ports = array_map(fn($port) => (array) $port, $ports);

        foreach ($ports as $port) {
            $degrees[$port['device_id']] = ($degrees[$port['device_id']] ?? 0) + 1;
        }

        return $degrees;
    }

    private function buildConnectivityGraph(array $nodeMapping): array
    {
        $deviceIds = array_keys($nodeMapping);
        $ports = $this->getActivePorts($deviceIds);
        $portsByDevice = $this->groupPortsByDevice($ports);

        return [
            'portsByDevice' => $portsByDevice,
            'links' => $this->discoverLinks($portsByDevice, $nodeMapping)
        ];
    }

    private function getActivePorts(array $deviceIds): array
    {
        $query = class_exists('\\App\\Models\\Port')
            ? \App\Models\Port::whereIn('device_id', $deviceIds)
                ->where('ifOperStatus', 'up')
                ->where('ifAdminStatus', 'up')
            : \DB::table('ports')->whereIn('device_id', $deviceIds)
                ->where('ifOperStatus', 'up')
                ->where('ifAdminStatus', 'up');

        $ports = $query->select('device_id', 'ifIndex', 'ifDescr')->get()->toArray();
        return array_map(fn($port) => (array) $port, $ports);
    }

    private function groupPortsByDevice(array $ports): array
    {
        $grouped = [];
        foreach ($ports as $port) {
            $grouped[$port['device_id']][] = $port;
        }
        return $grouped;
    }

    private function discoverLinks(array $portsByDevice, array $nodeMapping): array
    {
        $links = [];

        foreach ($portsByDevice as $deviceId => $devicePorts) {
            foreach ($devicePorts as $port) {
                $neighbor = $this->findPortNeighbor($port);
                if ($neighbor && isset($nodeMapping[$neighbor['device_id']])) {
                    $linkKey = $this->createLinkKey($deviceId, $neighbor['device_id']);

                    if (!isset($links[$linkKey])) {
                        $links[$linkKey] = [
                            'device_a' => min($deviceId, $neighbor['device_id']),
                            'device_b' => max($deviceId, $neighbor['device_id']),
                            'ports' => []
                        ];
                    }

                    $links[$linkKey]['ports'][] = [
                        'device_id' => $deviceId,
                        'port_id' => $port['ifIndex'],
                        'neighbor_device_id' => $neighbor['device_id'],
                        'neighbor_port_id' => $neighbor['ifIndex'] ?? null,
                    ];
                }
            }
        }

        return $links;
    }

    private function createLinkKey(int $deviceA, int $deviceB): string
    {
        return min($deviceA, $deviceB) . '-' . max($deviceA, $deviceB);
    }

    private function createDiscoveredLinks(Map $map, array $connectivityData, array $nodeMapping): void
    {
        foreach ($connectivityData['links'] as $linkData) {
            $nodeAId = $nodeMapping[$linkData['device_a']];
            $nodeBId = $nodeMapping[$linkData['device_b']];

            $ports = $this->findLinkPorts($linkData['ports']);

            Link::create([
                'map_id' => $map->id,
                'src_node_id' => $nodeAId,
                'dst_node_id' => $nodeBId,
                'port_id_a' => $ports['port_a'],
                'port_id_b' => $ports['port_b'],
                'bandwidth_bps' => null,
                'style' => [],
            ]);
        }
    }

    private function findLinkPorts(array $portData): array
    {
        $ports = ['port_a' => null, 'port_b' => null];

        foreach ($portData as $portInfo) {
            $deviceId = $portInfo['device_id'];
            $portId = $this->findPortId($deviceId, $portInfo['port_id']);

            if ($portInfo['device_id'] === $portData[0]['device_id']) {
                $ports['port_a'] = $portId;
            } else {
                $ports['port_b'] = $portId;
            }
        }

        return $ports;
    }
}
