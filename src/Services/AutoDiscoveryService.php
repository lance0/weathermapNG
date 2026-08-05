<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoDiscoveryService
{
    /** LibreNMS topology protocols we trust for neighbor discovery. */
    private const TOPOLOGY_PROTOCOLS = ['lldp', 'xdp', 'cdp'];

    private $gridLayout;

    public function __construct()
    {
        $this->gridLayout = new GridLayout(100, 100, 120, 8);
    }

    /**
     * Discover the topology around the map's candidate devices and seed
     * missing wmng nodes + links from LibreNMS LLDP/XDP/CDP data.
     *
     * Returns a summary array (nodes_added, links_added) for the UI.
     */
    public function discoverAndSeedMap(Map $map, array $params): array
    {
        $devices = $this->discoverDevices($params);
        $candidateIds = array_map('intval', array_column($devices, 'device_id'));

        if (empty($candidateIds)) {
            Log::info("WeathermapNG: Auto-discovery found no candidate devices for map {$map->id}.");
            return ['nodes_added' => 0, 'links_added' => 0];
        }

        $existingNodes = $this->getExistingNodeMapping($map);
        $nodeMapping = $this->createMissingNodes($map, $devices, $existingNodes, $params['minDegree']);
        $nodesAdded = count($nodeMapping) - count($existingNodes);

        $linkRows = $this->queryTopologyLinks($candidateIds);

        if (empty($linkRows)) {
            Log::info("WeathermapNG: Auto-discovery found no LibreNMS topology links for map {$map->id}.");
            return ['nodes_added' => $nodesAdded, 'links_added' => 0];
        }

        $portsByDevice = $this->getTopologyPorts($candidateIds);
        $links = $this->buildLinksFromTopology($linkRows, $nodeMapping, $portsByDevice);

        $linksAdded = $this->createDiscoveredLinks($map, $links, $nodeMapping);

        Log::info("WeathermapNG: Auto-discovery for map {$map->id} added {$nodesAdded} nodes and {$linksAdded} links.");

        return [
            'nodes_added' => $nodesAdded,
            'links_added' => $linksAdded,
        ];
    }

    public function validateDiscoveryParams(array $params): array
    {
        return [
            'minDegree' => max(0, (int) ($params['min_degree'] ?? 0)),
            'osFilter' => array_filter(array_map('trim', explode(',', trim((string) ($params['os'] ?? ''))))),
        ];
    }

    private function discoverDevices(array $params): array
    {
        $query = $this->buildDeviceQuery($params['osFilter']);
        $devices = $query->get()->toArray();

        return array_map(fn($device) => (array) $device, $devices);
    }

    private function buildDeviceQuery(array $osFilters): object
    {
        $baseQuery = class_exists('\\App\\Models\\Device')
            ? \App\Models\Device::where('disabled', 0)->where('ignore', 0)->select('device_id', 'hostname', 'os')
            : DB::table('devices')->where('disabled', 0)->where('ignore', 0)->select('device_id', 'hostname', 'os');

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

        foreach ($devices as $device) {
            $deviceId = (int) ($device['device_id'] ?? 0);

            if (!$deviceId || isset($nodeMapping[$deviceId])) {
                continue;
            }

            if ($minDegree > 0 && ($deviceDegrees[$deviceId] ?? 0) < $minDegree) {
                continue;
            }

            $position = $this->gridLayout->getNextPosition();

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

        if (empty($deviceIds)) {
            return $degrees;
        }

        $portsQuery = class_exists('\\App\\Models\\Port')
            ? \App\Models\Port::whereIn('device_id', $deviceIds)
                ->where('ifOperStatus', 'up')
                ->where('ifAdminStatus', 'up')
            : DB::table('ports')->whereIn('device_id', $deviceIds)
                ->where('ifOperStatus', 'up')
                ->where('ifAdminStatus', 'up');

        $ports = $portsQuery->select('device_id')->get()->toArray();
        $ports = array_map(fn($port) => (array) $port, $ports);

        foreach ($ports as $port) {
            $degrees[$port['device_id']] = ($degrees[$port['device_id']] ?? 0) + 1;
        }

        return $degrees;
    }

    /**
     * Pull LibreNMS topology links (LLDP/XDP/CDP) that touch any candidate
     * device, on either end (local or remote).
     */
    private function queryTopologyLinks(array $candidateIds): array
    {
        $query = class_exists('\\App\\Models\\Link')
            ? \App\Models\Link::whereIn('protocol', self::TOPOLOGY_PROTOCOLS)
            : DB::table('links')->whereIn('protocol', self::TOPOLOGY_PROTOCOLS);

        $query->where(function ($q) use ($candidateIds) {
            $q->whereIn('local_device_id', $candidateIds)
                ->orWhereIn('remote_device_id', $candidateIds);
        });

        $rows = $query->select(
            'local_device_id',
            'local_port_id',
            'remote_device_id',
            'remote_port_id',
            'protocol'
        )->get()->toArray();

        return array_map(fn($row) => (array) $row, $rows);
    }

    /**
     * Fetch port_id/ifIndex for all candidate devices so every links-table
     * port reference (which may be a port_id or an ifIndex) can be resolved.
     */
    private function getTopologyPorts(array $candidateIds): array
    {
        $query = class_exists('\\App\\Models\\Port')
            ? \App\Models\Port::whereIn('device_id', $candidateIds)
            : DB::table('ports')->whereIn('device_id', $candidateIds);

        $ports = $query->select('device_id', 'port_id', 'ifIndex')->get()->toArray();
        $ports = array_map(fn($port) => (array) $port, $ports);

        $grouped = [];
        foreach ($ports as $port) {
            $grouped[$port['device_id']][] = $port;
        }

        return $grouped;
    }

    /**
     * Map LibreNMS links-table rows to wmng device pairs, deduplicated by the
     * unordered device pair (createLinkKey) so bidirectional LLDP entries for
     * the same pair collapse into a single wmng link.
     */
    private function buildLinksFromTopology(array $linkRows, array $nodeMapping, array $portsByDevice): array
    {
        $links = [];

        foreach ($linkRows as $row) {
            $deviceA = (int) ($row['local_device_id'] ?? 0);
            $deviceB = (int) ($row['remote_device_id'] ?? 0);

            if (!$deviceA || !$deviceB || $deviceA === $deviceB) {
                continue;
            }

            // Both ends must correspond to wmng nodes on this map.
            if (!isset($nodeMapping[$deviceA]) || !isset($nodeMapping[$deviceB])) {
                continue;
            }

            $key = $this->createLinkKey($deviceA, $deviceB);

            if (!isset($links[$key])) {
                $links[$key] = [
                    'device_a' => min($deviceA, $deviceB),
                    'device_b' => max($deviceA, $deviceB),
                    'port_a' => null,
                    'port_b' => null,
                ];
            }

            // Resolve each end independently; a missing port skips only that end.
            $portA = $this->resolveTopologyPort($deviceA, $row['local_port_id'] ?? null, $portsByDevice);
            $portB = $this->resolveTopologyPort($deviceB, $row['remote_port_id'] ?? null, $portsByDevice);

            $this->attachPort($links[$key], $deviceA, $portA);
            $this->attachPort($links[$key], $deviceB, $portB);
        }

        return $links;
    }

    /**
     * Resolve a links-table port reference to a port_id. The reference may
     * already be a port_id, or it may be an ifIndex that needs resolution
     * against the same device's ports. Returns null when not found.
     */
    private function resolveTopologyPort(int $deviceId, $portRef, array $portsByDevice): ?int
    {
        $portRef = $portRef === null ? null : (int) $portRef;
        if ($portRef === null || $portRef <= 0) {
            return null;
        }

        $devicePorts = $portsByDevice[$deviceId] ?? [];

        foreach ($devicePorts as $port) {
            if ((int) ($port['port_id'] ?? 0) === $portRef) {
                return (int) $port['port_id'];
            }
        }

        foreach ($devicePorts as $port) {
            if ((int) ($port['ifIndex'] ?? 0) === $portRef) {
                return (int) $port['port_id'];
            }
        }

        return null;
    }

    private function attachPort(array &$link, int $deviceId, ?int $portId): void
    {
        if ($portId === null) {
            return;
        }

        if ($deviceId === $link['device_a']) {
            $link['port_a'] = $portId;
        } elseif ($deviceId === $link['device_b']) {
            $link['port_b'] = $portId;
        }
    }

    private function createLinkKey(int $deviceA, int $deviceB): string
    {
        return min($deviceA, $deviceB) . '-' . max($deviceA, $deviceB);
    }

    private function createDiscoveredLinks(Map $map, array $links, array $nodeMapping): int
    {
        $created = 0;

        foreach ($links as $linkData) {
            $srcNode = $nodeMapping[$linkData['device_a']];
            $dstNode = $nodeMapping[$linkData['device_b']];

            Link::create([
                'map_id' => $map->id,
                'src_node_id' => $srcNode,
                'dst_node_id' => $dstNode,
                'port_id_a' => $linkData['port_a'],
                'port_id_b' => $linkData['port_b'],
                'bandwidth_bps' => null,
                'style' => [],
            ]);

            $created++;
        }

        return $created;
    }

    private function applyLayoutAlgorithm(): void
    {
        // Layout positions are already applied during node creation
        // This method is a placeholder for future advanced layout algorithms
    }
}