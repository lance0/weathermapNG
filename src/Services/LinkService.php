<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;

class LinkService
{
    public function createLink(Map $map, array $data): Link
    {
        $this->validateLinkData($data);

        $srcNode = Node::find($data['src_node_id']);
        $dstNode = Node::find($data['dst_node_id']);

        $this->validateNodesBelongToMap($map, $srcNode, $dstNode);
        $this->validatePortDevicePairing($data, $srcNode, $dstNode);

        return Link::create([
            'map_id' => $map->id,
            'src_node_id' => $data['src_node_id'],
            'dst_node_id' => $data['dst_node_id'],
            'port_id_a' => $data['port_id_a'] ?? null,
            'port_id_b' => $data['port_id_b'] ?? null,
            'bandwidth_bps' => $data['bandwidth_bps'] ?? null,
            'style' => $data['style'] ?? [],
        ]);
    }

    public function updateLink(Map $map, Link $link, array $data): Link
    {
        $this->validateLinkOwnership($map, $link);

        $link->fill($data);
        $link->save();

        return $link->refresh();
    }

    public function deleteLink(Map $map, Link $link): void
    {
        $this->validateLinkOwnership($map, $link);
        $link->delete();
    }

    public function storeLinks(Map $map, array $linksData): void
    {
        $map->links()->delete();

        foreach ($linksData as $linkData) {
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
    }

    private function validateLinkData(array $data): void
    {
        $hasPorts = ($data['port_id_a'] ?? null) || ($data['port_id_b'] ?? null);

        if (!$hasPorts) {
            return;
        }

        if (empty($data['src_node_id']) || empty($data['dst_node_id'])) {
            throw new \InvalidArgumentException('Both source and destination nodes are required when using ports');
        }
    }

    private function validateNodesBelongToMap(Map $map, ?Node $srcNode, ?Node $dstNode): void
    {
        if (!$srcNode || !$dstNode) {
            throw new \InvalidArgumentException('Invalid node(s)');
        }

        if ($srcNode->map_id !== $map->id || $dstNode->map_id !== $map->id) {
            throw new \InvalidArgumentException('Node(s) do not belong to this map');
        }
    }

    private function validatePortDevicePairing(array $data, ?Node $srcNode, ?Node $dstNode): void
    {
        $this->validateSourcePortDevice($data, $srcNode);
        $this->validateDestinationPortDevice($data, $dstNode);
    }

    private function validateSourcePortDevice(array $data, ?Node $srcNode): void
    {
        $portId = $data['port_id_a'] ?? null;

        if (!$portId) {
            return;
        }

        $this->validatePortBelongsToDevice($portId, $srcNode);
    }

    private function validateDestinationPortDevice(array $data, ?Node $dstNode): void
    {
        $portId = $data['port_id_b'] ?? null;

        if (!$portId) {
            return;
        }

        $this->validatePortBelongsToDevice($portId, $dstNode);
    }

    private function validatePortBelongsToDevice(int $portId, ?Node $node): void
    {
        if (!$node || !$node->device_id) {
            return;
        }

        $port = $this->fetchPort($portId);

        if (!$port || $port->device_id != $node->device_id) {
            throw new \InvalidArgumentException('Port does not belong to device');
        }
    }

    private function fetchPort(int $portId): ?object
    {
        try {
            $portClass = '\\App\\Models\\Port';
            if (class_exists($portClass)) {
                return $portClass::find($portId);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function validateLinkOwnership(Map $map, Link $link): void
    {
        if ($link->map_id !== $map->id) {
            throw new \RuntimeException('Link does not belong to map');
        }
    }
}
