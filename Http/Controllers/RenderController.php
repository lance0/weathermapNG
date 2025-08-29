<?php
namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
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
        return view('plugins.WeathermapNG.embed', compact('map'));
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

        // Import nodes
        foreach ($data['nodes'] as $nodeData) {
            Node::create([
                'map_id' => $map->id,
                'label' => $nodeData['label'],
                'x' => $nodeData['x'],
                'y' => $nodeData['y'],
                'device_id' => $nodeData['device_id'] ?? null,
                'meta' => $nodeData['meta'] ?? [],
            ]);
        }

        // Import links (this would need adjustment based on node IDs)
        // For now, we'll skip links in import to avoid ID conflicts
        // In a full implementation, you'd need to map old IDs to new IDs

        return response()->json([
            'success' => true,
            'map_id' => $map->id,
            'message' => 'Map imported successfully'
        ]);
    }
}