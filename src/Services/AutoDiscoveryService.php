<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoDiscoveryService
{
    private $gridLayout;

    public function __construct()
    {
        $this->gridLayout = new GridLayout(100, 100, 120, 8);
    }

    /**
     * Auto-discovery is currently disabled.
     *
     * The ifIndex-based neighbor matching doesn't work reliably.
     * Future versions will use LibreNMS LLDP/CDP data from the links table.
     */
    public function discoverAndSeedMap(Map $map, array $params): array
    {
        Log::warning("WeathermapNG: Auto-discovery is disabled. Use manual node/link creation instead.");
        return [];
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

    private function buildConnectivityGraph(array $nodeMapping): array
    {
        $deviceIds = array_keys($nodeMapping);
        if (empty($deviceIds)) {
            return ['portsByDevice' => [], 'links' => []];
        }

        $ports = $this->getActivePorts($deviceIds);
        $portsByDevice = $this->groupPortsByDevice($ports);

        return [
            'portsByDevice' => $portsByDevice,
            'links' => $this->discoverLinks($portsByDevice, $nodeMapping)
        ];
    }

    private function getActivePorts(array $deviceIds): array
    {
        $query = class_exists('\\App\\Models\\Port')
            ? \App\Models\Port::whereIn('device_id', $deviceIds)
                ->where('ifOperStatus', 'up')
                ->where('ifAdminStatus', 'up')
            : DB::table('ports')->whereIn('device_id', $deviceIds)
                ->where('ifOperStatus', 'up')
                ->where('ifAdminStatus', 'up');

        $ports = $query->select('device_id', 'ifIndex', 'ifDescr')->get()->toArray();
        return array_map(fn($port) => (array) $port, $ports);
    }

    private function groupPortsByDevice(array $ports): array
    {
        $grouped = [];
        foreach ($ports as $port) {
            $grouped[$port['device_id']][] = $port;
        }
        return $grouped;
    }

    private function discoverLinks(array $portsByDevice, array $nodeMapping): array
    {
        $links = [];

        foreach ($portsByDevice as $deviceId => $devicePorts) {
            foreach ($devicePorts as $port) {
                $neighbor = $this->findPortNeighbor($port);

                if ($neighbor && isset($nodeMapping[$neighbor['device_id']])) {
                    $linkKey = $this->createLinkKey($deviceId, $neighbor['device_id']);

                    if (!isset($links[$linkKey])) {
                        $links[$linkKey] = [
                            'device_a' => min($deviceId, $neighbor['device_id']),
                            'device_b' => max($deviceId, $neighbor['device_id']),
                            'ports' => []
                        ];
                    }

                    $links[$linkKey]['ports'][] = [
                        'device_id' => $deviceId,
                        'port_id' => $port['ifIndex'],
                        'neighbor_device_id' => $neighbor['device_id'],
                        'neighbor_port_id' => $neighbor['ifIndex'] ?? null,
                    ];
                }
            }
        }

        return $links;
    }

    private function findPortNeighbor(array $port): ?array
    {
        try {
            $query = class_exists('\\App\\Models\\Port')
                ? \App\Models\Port::where('ifIndex', $port['ifIndex'])
                    ->where('device_id', '!=', $port['device_id'])
                : DB::table('ports')->where('ifIndex', $port['ifIndex'])
                    ->where('device_id', '!=', $port['device_id']);

            $neighbor = $query->select('device_id', 'ifIndex')->first();

            return $neighbor ? (array) $neighbor : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function createLinkKey(int $deviceA, int $deviceB): string
    {
        return min($deviceA, $deviceB) . '-' . max($deviceA, $deviceB);
    }

    private function createDiscoveredLinks(Map $map, array $links, array $nodeMapping): void
    {
        foreach ($links as $linkData) {
            $nodeAId = $nodeMapping[$linkData['device_a']];
            $nodeBId = $nodeMapping[$linkData['device_b']];

            $ports = $this->findLinkPorts($linkData['ports']);

            Link::create([
                'map_id' => $map->id,
                'src_node_id' => $nodeAId,
                'dst_node_id' => $nodeBId,
                'port_id_a' => $ports['port_a'],
                'port_id_b' => $ports['port_b'],
                'bandwidth_bps' => null,
                'style' => [],
            ]);
        }
    }

    private function findLinkPorts(array $portData): array
    {
        $ports = ['port_a' => null, 'port_b' => null];

        if (empty($portData)) {
            return $ports;
        }

        foreach ($portData as $portInfo) {
            $portId = $this->findPortId($portInfo['device_id'], $portInfo['port_id']);

            if ($portInfo['device_id'] === $portData[0]['device_id']) {
                $ports['port_a'] = $portId;
            } else {
                $ports['port_b'] = $portId;
            }
        }

        return $ports;
    }

    private function findPortId(int $deviceId, ?int $ifIndex): ?int
    {
        try {
            $query = class_exists('\\App\\Models\\Port')
                ? \App\Models\Port::where('device_id', $deviceId)
                    ->where('ifIndex', $ifIndex)
                : DB::table('ports')->where('device_id', $deviceId)
                    ->where('ifIndex', $ifIndex);

            $port = $query->select('port_id')->first();
            return $port ? $port['port_id'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function applyLayoutAlgorithm(): void
    {
        // Layout positions are already applied during node creation
        // This method is a placeholder for future advanced layout algorithms
    }
}
