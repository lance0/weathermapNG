<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Services\NodeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MapNodeController
{
    private $nodeService;

    public function __construct(NodeService $nodeService)
    {
        $this->nodeService = $nodeService;
    }

    public function store(Request $request, Map $map): JsonResponse
    {
        $validated = $request->validate([
            'nodes' => 'required|array',
            'nodes.*.label' => 'required|string|max:255',
            'nodes.*.x' => 'required|numeric',
            'nodes.*.y' => 'required|numeric',
            'nodes.*.device_id' => 'nullable|integer',
        ]);

        $this->nodeService->storeNodes($map, $validated['nodes']);

        return response()->json(['success' => true]);
    }

    public function create(Request $request, Map $map): JsonResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'x' => 'required|numeric',
            'y' => 'required|numeric',
            'device_id' => 'nullable|integer',
            'meta' => 'array',
        ]);

        $node = $this->nodeService->createNode($map, $data);

        return response()->json(['success' => true, 'node' => $node]);
    }

    public function update(Request $request, Map $map, Node $node): JsonResponse
    {
        $data = $request->validate([
            'label' => 'sometimes|string|max:255',
            'x' => 'sometimes|numeric',
            'y' => 'sometimes|numeric',
            'device_id' => 'sometimes|nullable|integer',
            'meta' => 'sometimes|array',
        ]);

        try {
            $node = $this->nodeService->updateNode($map, $node, $data);
            return response()->json(['success' => true, 'node' => $node]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function delete(Map $map, Node $node): JsonResponse
    {
        try {
            $this->nodeService->deleteNode($map, $node);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
