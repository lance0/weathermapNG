<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\Services\LinkService;
use Illuminate\Http\Request;

class MapLinkController
{
    private $linkService;

    public function __construct(LinkService $linkService)
    {
        $this->linkService = $linkService;
    }

    public function store(Request $request, Map $map)
    {
        $validated = $request->validate([
            'links' => 'required|array',
            'links.*.src_node_id' => 'required|integer|exists:wmng_nodes,id',
            'links.*.dst_node_id' => 'required|integer|exists:wmng_nodes,id',
            'links.*.port_id_a' => 'nullable|integer',
            'links.*.port_id_b' => 'nullable|integer',
            'links.*.bandwidth_bps' => 'nullable|integer',
        ]);

        $this->linkService->storeLinks($map, $validated['links']);

        return response()->json(['success' => true]);
    }

    public function create(Request $request, Map $map)
    {
        $data = $request->validate([
            'src_node_id' => 'required|integer|exists:wmng_nodes,id',
            'dst_node_id' => 'required|integer|exists:wmng_nodes,id',
            'port_id_a' => 'nullable|integer',
            'port_id_b' => 'nullable|integer',
            'bandwidth_bps' => 'nullable|integer',
            'style' => 'array',
        ]);

        try {
            $link = $this->linkService->createLink($map, $data);
            return response()->json(['success' => true, 'link' => $link]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, Map $map, Link $link)
    {
        $data = $request->validate([
            'src_node_id' => 'sometimes|integer',
            'dst_node_id' => 'sometimes|integer',
            'port_id_a' => 'sometimes|nullable|integer',
            'port_id_b' => 'sometimes|nullable|integer',
            'bandwidth_bps' => 'sometimes|nullable|integer',
            'style' => 'sometimes|array',
        ]);

        try {
            $link = $this->linkService->updateLink($map, $link, $data);
            return response()->json(['success' => true, 'link' => $link]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function delete(Map $map, Link $link)
    {
        try {
            $this->linkService->deleteLink($map, $link);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
