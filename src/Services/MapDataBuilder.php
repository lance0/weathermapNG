<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;

class MapDataBuilder
{
    private $portUtil;
    private $alertService;

    public function __construct(PortUtilService $portUtil, AlertService $alertService)
    {
        $this->portUtil = $portUtil;
        $this->alertService = $alertService;
    }

    public function buildLiveData(Map $map): array
    {
        return [
            'ts' => time(),
            'links' => $this->buildLinkData($map),
            'nodes' => $this->buildNodeData($map),
            'alerts' => $this->buildAlertData($map),
        ];
    }

    public function buildLinkData(Map $map): array
    {
        $linkData = [];
        foreach ($map->links as $link) {
            $linkData[$link->id] = $this->portUtil->linkUtilBits([
                'port_id_a' => $link->port_id_a,
                'port_id_b' => $link->port_id_b,
                'bandwidth_bps' => $link->bandwidth_bps,
            ]);
        }
        return $linkData;
    }

    public function buildNodeData(Map $map): array
    {
        $nodeData = [];
        $portsByNode = $this->buildPortsByNode($map);
        $linkData = $this->buildLinkData($map);

        foreach ($map->nodes as $node) {
            $nodeData[$node->id] = $this->buildNodeMetrics($node, $portsByNode, $linkData);
        }

        return $nodeData;
    }

    public function buildAlertData(Map $map): array
    {
        $deviceIds = $this->collectDeviceIds($map);
        $deviceAlerts = $this->alertService->deviceAlerts($deviceIds);

        return [
            'nodes' => $this->mapDeviceAlertsToNodes($map, $deviceAlerts),
            'links' => $this->buildLinkAlerts($map),
        ];
    }

    private function buildNodeMetrics(Node $node, array $portsByNode, array $linkData): array
    {
        $portIds = $portsByNode[$node->id] ?? [];
        $trafficData = $this->aggregateNodeTraffic($node, $portIds, $linkData);

        return array_merge(
            [
                'status' => $this->getNodeStatus($node),
                'device_id' => $node->device_id,
            ],
            $trafficData
        );
    }

    private function buildPortsByNode(Map $map): array
    {
        $portsByNode = [];
        foreach ($map->links as $link) {
            $this->addPortToNode($portsByNode, $link->src_node_id, $link->port_id_a);
            $this->addPortToNode($portsByNode, $link->dst_node_id, $link->port_id_b);
        }
        return $portsByNode;
    }

    private function addPortToNode(array &$portsByNode, ?int $nodeId, ?int $portId): void
    {
        if ($nodeId && $portId) {
            $portsByNode[$nodeId] = $portsByNode[$nodeId] ?? [];
            $portsByNode[$nodeId][] = (int) $portId;
        }
    }

    private function collectDeviceIds(Map $map): array
    {
        $deviceIds = [];
        foreach ($map->nodes as $node) {
            if ($node->device_id) {
                $deviceIds[] = (int) $node->device_id;
            }
        }
        return array_values(array_unique($deviceIds));
    }

    private function getNodeStatus(Node $node): string
    {
        if (!$node->device_id) {
            return 'unknown';
        }

        try {
            $device = $this->fetchDevice($node->device_id);
            if ($device) {
                return $this->parseDeviceStatus($device);
            }
        } catch (\Exception $e) {
        }

        return 'unknown';
    }

    private function fetchDevice(int $deviceId)
    {
        if (class_exists('App\\Models\\Device')) {
            return \App\Models\Device::find($deviceId);
        }

        return \DB::table('devices')->where('device_id', $deviceId)->first();
    }

    private function parseDeviceStatus($device): string
    {
        $status = $device->status ?? 0;
        return (int) $status === 1 ? 'up' : 'down';
    }

    private function aggregateNodeTraffic(Node $node, array $portIds, array $linkData): array
    {
        $traffic = $this->sumPortTraffic($portIds);

        if ($traffic['sum'] === 0) {
            $traffic = $this->sumLinkTraffic($node, $linkData);
        }

        if ($traffic['sum'] === 0 && $node->device_id) {
            $traffic = $this->getDeviceTraffic($node->device_id);
        }

        if ($traffic['sum'] === 0 && !$node->device_id && $node->label) {
            $traffic = $this->guessDeviceTraffic($node->label);
        }

        return [
            'traffic' => $traffic,
        ];
    }

    private function sumPortTraffic(array $portIds): array
    {
        $inSum = 0;
        $outSum = 0;

        foreach (array_unique($portIds) as $portId) {
            try {
                $portData = $this->portUtil->getPortData((int) $portId);
                $inSum += (int) ($portData['in'] ?? 0);
                $outSum += (int) ($portData['out'] ?? 0);
            } catch (\Throwable $e) {
            }
        }

        return $this->formatTrafficData($inSum, $outSum, 'ports');
    }

    private function sumLinkTraffic(Node $node, array $linkData): array
    {
        $inSum = 0;
        $outSum = 0;

        foreach ($linkData as $linkId => $linkInfo) {
            if ($this->isLinkConnectedToNode($node, $linkId)) {
                $inSum += (int) ($linkInfo['in_bps'] ?? 0);
                $outSum += (int) ($linkInfo['out_bps'] ?? 0);
            }
        }

        return $this->formatTrafficData($inSum, $outSum, 'links');
    }

    private function isLinkConnectedToNode(Node $node, int $linkId): bool
    {
        $link = collect($node->map->links)->first(fn($lnk) => $lnk->id == $linkId);
        return $link && ($link->src_node_id == $node->id || $link->dst_node_id == $node->id);
    }

    private function getDeviceTraffic(int $deviceId): array
    {
        $agg = $this->portUtil->deviceAggregateBits($deviceId, 24);
        $inSum = (int) ($agg['in'] ?? 0);
        $outSum = (int) ($agg['out'] ?? 0);

        return $this->formatTrafficData($inSum, $outSum, 'device');
    }

    private function guessDeviceTraffic(string $label): array
    {
        try {
            $row = \DB::table('devices')
                ->select('device_id')
                ->where('hostname', $label)
                ->orWhere('sysName', $label)
                ->first();

            if ($row && isset($row->device_id)) {
                $agg = $this->portUtil->deviceAggregateBits((int) $row->device_id, 24);
                $inSum = (int) ($agg['in'] ?? 0);
                $outSum = (int) ($agg['out'] ?? 0);

                return $this->formatTrafficData($inSum, $outSum, 'device_guess');
            }
        } catch (\Throwable $e) {
        }

        return $this->formatTrafficData(0, 0, 'none');
    }

    private function formatTrafficData(int $inSum, int $outSum, string $source): array
    {
        return [
            'in_bps' => $inSum,
            'out_bps' => $outSum,
            'sum_bps' => $inSum + $outSum,
            'source' => ($inSum + $outSum) > 0 ? $source : 'none',
        ];
    }

    private function mapDeviceAlertsToNodes(Map $map, array $deviceAlerts): array
    {
        $nodeAlerts = [];
        foreach ($map->nodes as $node) {
            if ($node->device_id && isset($deviceAlerts[(int) $node->device_id])) {
                $nodeAlerts[$node->id] = $deviceAlerts[(int) $node->device_id];
            }
        }
        return $nodeAlerts;
    }

    private function buildLinkAlerts(Map $map): array
    {
        $portIds = $this->collectPortIdsFromLinks($map);
        $portAlerts = $this->alertService->portAlerts($portIds);

        $linkAlerts = [];
        foreach ($map->links as $link) {
            $alertInfo = $this->calculateLinkAlerts($link, $portAlerts);
            if ($alertInfo['count'] > 0) {
                $linkAlerts[$link->id] = $alertInfo;
            }
        }

        return $linkAlerts;
    }

    private function collectPortIdsFromLinks(Map $map): array
    {
        $portIds = [];
        foreach ($map->links as $link) {
            if ($link->port_id_a) {
                $portIds[] = (int) $link->port_id_a;
            }
            if ($link->port_id_b) {
                $portIds[] = (int) $link->port_id_b;
            }
        }
        return array_values(array_unique($portIds));
    }

    private function calculateLinkAlerts(Link $link, array $portAlerts): array
    {
        $alertCount = 0;
        $maxSeverity = null;

        foreach ([(int) $link->port_id_a, (int) $link->port_id_b] as $portId) {
            if ($portId && isset($portAlerts[$portId])) {
                $alertCount += $portAlerts[$portId]['count'];
                $maxSeverity = $this->compareSeverity($maxSeverity, $portAlerts[$portId]['severity']);
            }
        }

        return [
            'count' => $alertCount,
            'severity' => $maxSeverity ?? 'warning',
        ];
    }

    private function compareSeverity(?string $current, string $new): string
    {
        if (!$current) {
            return $new;
        }

        $severityWeight = ['ok' => 0, 'warning' => 1, 'critical' => 2, 'severe' => 3];
        $currentWeight = $severityWeight[$current] ?? 0;
        $newWeight = $severityWeight[$new] ?? 0;

        return $currentWeight >= $newWeight ? $current : $new;
    }
}
