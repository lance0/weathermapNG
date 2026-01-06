<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NodeService
{
    public function createNode(Map $map, array $data): Node
    {
        return Node::create([
            'map_id' => $map->id,
            'label' => $data['label'] ?? 'New Node',
            'x' => $data['x'] ?? 0,
            'y' => $data['y'] ?? 0,
            'device_id' => $data['device_id'] ?? null,
            'meta' => $data['meta'] ?? [],
        ]);
    }

    public function updateNode(Map $map, Node $node, array $data): Node
    {
        $this->validateNodeOwnership($map, $node);

        $node->fill($data);
        $node->save();

        return $node->refresh();
    }

    public function deleteNode(Map $map, Node $node): void
    {
        $this->validateNodeOwnership($map, $node);

        try {
            DB::transaction(function () use ($node, $map) {
                $map->links()
                    ->where('src_node_id', $node->id)
                    ->orWhere('dst_node_id', $node->id)
                    ->delete();

                $node->delete();
            });
        } catch (\Exception $e) {
            Log::error("Failed to delete node {$node->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function storeNodes(Map $map, array $nodesData): void
    {
        try {
            DB::transaction(function () use ($map, $nodesData) {
                $map->nodes()->delete();

                foreach ($nodesData as $nodeData) {
                    Node::create([
                        'map_id' => $map->id,
                        'label' => $nodeData['label'] ?? 'Node',
                        'x' => $nodeData['x'] ?? 0,
                        'y' => $nodeData['y'] ?? 0,
                        'device_id' => $nodeData['device_id'] ?? null,
                        'meta' => $nodeData['meta'] ?? [],
                    ]);
                }
            });
        } catch (\Exception $e) {
            Log::error("Failed to store nodes for map {$map->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function validateNodeOwnership(Map $map, Node $node): void
    {
        if ($node->map_id !== $map->id) {
            throw new \RuntimeException("Node {$node->id} does not belong to map {$map->id}");
        }
    }
}
