<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;

class MapDataBuilder
{
    private $nodeDataService;
    private $linkDataService;
    private $alertService;

    public function __construct(
        NodeDataService $nodeDataService,
        LinkDataService $linkDataService,
        AlertService $alertService
    ) {
        $this->nodeDataService = $nodeDataService;
        $this->linkDataService = $linkDataService;
        $this->alertService = $alertService;
    }

    public function buildLiveData(Map $map): array
    {
        return [
            'ts' => time(),
            'links' => $this->linkDataService->buildLinkData($map),
            'nodes' => $this->nodeDataService->buildNodeData($map),
            'alerts' => $this->buildAlertData($map),
        ];
    }

    private function buildAlertData(Map $map): array
    {
        $deviceIds = $this->collectDeviceIds($map);
        $deviceAlerts = $this->alertService->deviceAlerts($deviceIds);

        return [
            'nodes' => $this->mapDeviceAlertsToNodes($map, $deviceAlerts),
            'links' => $this->linkDataService->buildLinkAlerts($map),
        ];
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
}
