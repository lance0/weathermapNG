<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;

class NodeDataService
{
    private $portUtil;
    private $deviceDataService;

    public function __construct(PortUtilService $portUtil, DeviceDataService $deviceDataService)
    {
        $this->portUtil = $portUtil;
        $this->deviceDataService = $deviceDataService;
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

    private function buildNodeMetrics(Node $node, array $portsByNode, array $linkData): array
    {
        $portIds = $portsByNode[$node->id] ?? [];
        $trafficData = $this->aggregateNodeTraffic($node, $portIds, $linkData);
        $status = $this->deviceDataService->getNodeStatus($node);

        return array_merge(
            [
                'device_id' => $node->device_id,
                'status' => $status,
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

    private function aggregateNodeTraffic(Node $node, array $portIds, array $linkData): array
    {
        $traffic = $this->sumPortTraffic($portIds);

        if ($traffic['sum'] === 0) {
            $traffic = $this->sumLinkTraffic($node, $linkData);
        }

        if ($traffic['sum'] === 0 && $node->device_id) {
            $traffic = $this->deviceDataService->getDeviceTraffic($node->device_id);
        }

        if ($traffic['sum'] === 0 && !$node->device_id && $node->label) {
            $traffic = $this->deviceDataService->guessDeviceTraffic($node->label);
        }

        return $traffic;
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

    private function formatTrafficData(int $inSum, int $outSum, string $source): array
    {
        return [
            'in_bps' => $inSum,
            'out_bps' => $outSum,
            'sum_bps' => $inSum + $outSum,
            'source' => ($inSum + $outSum) > 0 ? $source : 'none',
        ];
    }

    private function buildLinkData(Map $map): array
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
}
