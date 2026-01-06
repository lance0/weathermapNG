<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;

class NodeService
{
    public function createNode(Map $map, array $data): Node
    {
        return Node::create([
            'map_id' => $map->id,
            'label' => $data['label'],
            'x' => $data['x'],
            'y' => $data['y'],
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

        $map->links()
            ->where('src_node_id', $node->id)
            ->orWhere('dst_node_id', $node->id)
            ->delete();

        $node->delete();
    }

    public function storeNodes(Map $map, array $nodesData): void
    {
        $map->nodes()->delete();

        foreach ($nodesData as $nodeData) {
            Node::create([
                'map_id' => $map->id,
                'label' => $nodeData['label'],
                'x' => $nodeData['x'],
                'y' => $nodeData['y'],
                'device_id' => $nodeData['device_id'] ?? null,
                'meta' => $nodeData['meta'] ?? [],
            ]);
        }
    }

    private function validateNodeOwnership(Map $map, Node $node): void
    {
        if ($node->map_id !== $map->id) {
            throw new \RuntimeException('Node does not belong to map');
        }
    }
}
