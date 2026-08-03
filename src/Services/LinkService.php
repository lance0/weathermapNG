<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LinkService
{
    public function createLink(Map $map, array $data): Link
    {
        $this->validateLinkForMap($map, $data);

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

        $newData = array_merge(
            [
                'src_node_id' => $link->src_node_id,
                'dst_node_id' => $link->dst_node_id,
                'port_id_a' => $link->port_id_a,
                'port_id_b' => $link->port_id_b,
            ],
            $data
        );
        $this->validateLinkForMap($map, $newData);

        $link->fill($data);
        $link->save();

        return $link->refresh();
    }

    public function deleteLink(Map $map, Link $link): void
    {
        $this->validateLinkOwnership($map, $link);
        $link->delete();
    }

    private function validateLinkForMap(Map $map, array $linkData): void
    {
        $this->validateLinkData($linkData);

        $srcNode = Node::find($linkData['src_node_id']);
        $dstNode = Node::find($linkData['dst_node_id']);

        $this->validateNodesBelongToMap($map, $srcNode, $dstNode);
        $this->validatePortDevicePairing($linkData, $srcNode, $dstNode);
    }

    /**
     * Batch validation variant of validateLinkForMap using pre-fetched maps.
     * Avoids per-link Node::find() and fetchPort() queries during storeLinks.
     *
     * @param \Illuminate\Support\Collection<int, Node> $nodesById  Nodes keyed by id, already filtered to the map.
     * @param array<int, object|null>                   $portsById  Ports keyed by port_id.
     */
    private function validateLinkForMapBatch(Map $map, array $linkData, $nodesById, array $portsById): void
    {
        $this->validateLinkData($linkData);

        $srcNode = $nodesById[$linkData['src_node_id']] ?? null;
        $dstNode = $nodesById[$linkData['dst_node_id']] ?? null;

        $this->validateNodesBelongToMap($map, $srcNode, $dstNode);
        $this->validatePortDevicePairingBatch($linkData, $srcNode, $dstNode, $portsById);
    }

    public function storeLinks(Map $map, array $linksData): void
    {
        if (empty($linksData) && $map->links()->exists()) {
            throw new \InvalidArgumentException('Cannot replace links with an empty array for an existing map');
        }

        $nodesById = collect($linksData)
            ->flatMap(fn ($l) => [$l['src_node_id'] ?? null, $l['dst_node_id'] ?? null])
            ->filter()
            ->unique()
            ->pipe(fn ($ids) => Node::whereIn('id', $ids)->where('map_id', $map->id)->get()->keyBy('id'));

        $portsById = $this->batchFetchPorts(
            collect($linksData)
                ->flatMap(fn ($l) => [$l['port_id_a'] ?? null, $l['port_id_b'] ?? null])
                ->filter()
                ->unique()
                ->all()
        );

        try {
            DB::transaction(function () use ($map, $linksData, $nodesById, $portsById) {
                $map->links()->delete();

                foreach ($linksData as $linkData) {
                    $this->validateLinkForMapBatch($map, $linkData, $nodesById, $portsById);

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
            });
        } catch (\Exception $e) {
            Log::error("Failed to store links for map {$map->id}: " . $e->getMessage());
            throw $e;
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

        if (!$port) {
            throw new \InvalidArgumentException("Port {$portId} not found");
        }

        if ($port->device_id != $node->device_id) {
            throw new \InvalidArgumentException("Port {$portId} does not belong to device {$node->device_id}");
        }
    }

    /**
     * Batch variant of validatePortDevicePairing using a pre-fetched port map.
     *
     * @param array<int, object|null> $portsById Ports keyed by port_id.
     */
    private function validatePortDevicePairingBatch(array $data, ?Node $srcNode, ?Node $dstNode, array $portsById): void
    {
        $this->validateSourcePortDeviceBatch($data, $srcNode, $portsById);
        $this->validateDestinationPortDeviceBatch($data, $dstNode, $portsById);
    }

    private function validateSourcePortDeviceBatch(array $data, ?Node $srcNode, array $portsById): void
    {
        $portId = $data['port_id_a'] ?? null;

        if (!$portId) {
            return;
        }

        $this->validatePortBelongsToDeviceBatch($portId, $srcNode, $portsById);
    }

    private function validateDestinationPortDeviceBatch(array $data, ?Node $dstNode, array $portsById): void
    {
        $portId = $data['port_id_b'] ?? null;

        if (!$portId) {
            return;
        }

        $this->validatePortBelongsToDeviceBatch($portId, $dstNode, $portsById);
    }

    /**
     * Batch variant of validatePortBelongsToDevice using a pre-fetched port map.
     *
     * @param array<int, object|null> $portsById Ports keyed by port_id.
     */
    private function validatePortBelongsToDeviceBatch(int $portId, ?Node $node, array $portsById): void
    {
        if (!$node || !$node->device_id) {
            return;
        }

        $port = $portsById[$portId] ?? null;

        if (!$port) {
            throw new \InvalidArgumentException("Port {$portId} not found");
        }

        if ($port->device_id != $node->device_id) {
            throw new \InvalidArgumentException("Port {$portId} does not belong to device {$node->device_id}");
        }
    }

    private function fetchPort(int $portId): ?object
    {
        try {
            $portClass = '\\App\\Models\\Port';
            if (class_exists($portClass)) {
                return $portClass::find($portId);
            }

            return DB::table('ports')->where('port_id', $portId)->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch a batch of ports keyed by port_id in a single query.
     * Mirrors fetchPort()'s class_exists('\App\Models\Port') fallback path.
     *
     * @param array<int, int|string> $portIds
     * @return array<int, object|null>  Ports keyed by port_id.
     */
    private function batchFetchPorts(array $portIds): array
    {
        $portIds = array_values(array_filter($portIds));
        if (empty($portIds)) {
            return [];
        }

        try {
            $portClass = '\\App\\Models\\Port';
            if (class_exists($portClass)) {
                return $portClass::whereIn('port_id', $portIds)->get()->keyBy('port_id')->all();
            }

            return DB::table('ports')->whereIn('port_id', $portIds)->get()->keyBy('port_id')->all();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function validateLinkOwnership(Map $map, Link $link): void
    {
        if ($link->map_id !== $map->id) {
            throw new \RuntimeException("Link {$link->id} does not belong to map {$map->id}");
        }
    }
}
