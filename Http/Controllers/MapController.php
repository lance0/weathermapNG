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
            $devices = dbFetchRows("SELECT device_id, hostname, sysName FROM devices WHERE disabled = 0 AND ignore = 0 ORDER BY hostname");
            return collect($devices)->map(function($device) {
                return (object) $device;
            });
        } catch (\Exception $e) {
            return collect([]);
        }
    }
}
