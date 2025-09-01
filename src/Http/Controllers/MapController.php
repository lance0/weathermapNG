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
        return view('plugins.WeathermapNG.index', compact('maps'));
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

        return view('plugins.WeathermapNG.editor', compact('map', 'devices'));
    }

    public function editorD3(Map $map)
    {
        $map->load(['nodes', 'links']);
        $title = 'WeathermapNG - D3 Editor';
        return view('plugins.WeathermapNG.editor-d3', compact('map', 'title'));
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
        $data = $this->validate($request, [
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
        $data = $this->validate($request, [
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
        $data = $this->validate($request, [
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
            'links.*.src_node_id' => 'required|integer',
            'links.*.dst_node_id' => 'required|integer',
            'links.*.port_id_a' => 'nullable|integer',
            'links.*.port_id_b' => 'nullable|integer',
            'links.*.bandwidth_bps' => 'nullable|integer',
            'links.*.style' => 'array',
        ]);

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

        if (isset($data['nodes'])) {
            $map->nodes()->delete();
            foreach ($data['nodes'] as $n) {
                Node::create([
                    'map_id' => $map->id,
                    'label' => $n['label'],
                    'x' => $n['x'],
                    'y' => $n['y'],
                    'device_id' => $n['device_id'] ?? null,
                    'meta' => $n['meta'] ?? [],
                ]);
            }
        }

        if (isset($data['links'])) {
            $map->links()->delete();
            foreach ($data['links'] as $l) {
                Link::create([
                    'map_id' => $map->id,
                    'src_node_id' => $l['src_node_id'],
                    'dst_node_id' => $l['dst_node_id'],
                    'port_id_a' => $l['port_id_a'] ?? null,
                    'port_id_b' => $l['port_id_b'] ?? null,
                    'bandwidth_bps' => $l['bandwidth_bps'] ?? null,
                    'style' => $l['style'] ?? [],
                ]);
            }
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

        $data = $this->validate($request, [
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

        $data = $this->validate($request, [
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
}
