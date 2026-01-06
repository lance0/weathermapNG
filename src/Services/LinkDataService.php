<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Link;

class LinkDataService
{
    private $alertService;

    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    public function buildLinkData(Map $map): array
    {
        $linkData = [];
        foreach ($map->links as $link) {
            $linkData[$link->id] = $this->buildLinkAlerts($link);
        }
        return $linkData;
    }

    public function buildLinkAlerts(Map $map): array
    {
        $linkAlerts = [];
        foreach ($map->links as $link) {
            $alertInfo = $this->buildLinkAlertInfo($link);
            if ($alertInfo['alert_count'] > 0) {
                $linkAlerts[$link->id] = $alertInfo;
            }
        }
        return $linkAlerts;
    }

    private function buildLinkAlertInfo(Link $link): array
    {
        $portIds = array_filter([$link->port_id_a, $link->port_id_b]);
        $portAlerts = $this->alertService->portAlerts($portIds);
        $alertInfo = $this->calculateLinkAlertInfo($link, $portAlerts);

        return array_merge($this->getLinkBaseData($link), $alertInfo);
    }

    private function getLinkBaseData(Link $link): array
    {
        return [
            'in_bps' => 0,
            'out_bps' => 0,
            'pct' => null,
        ];
    }

    private function calculateLinkAlertInfo(Link $link, array $portAlerts): array
    {
        $alertCount = 0;
        $maxSeverity = null;

        foreach ([$link->port_id_a, $link->port_id_b] as $portId) {
            if ($portId && isset($portAlerts[$portId])) {
                $alertCount += $portAlerts[$portId]['count'];
                $maxSeverity = $this->compareSeverity($maxSeverity, $portAlerts[$portId]['severity']);
            }
        }

        if ($alertCount === 0) {
            return [];
        }

        return [
            'alert_count' => $alertCount,
            'alert_severity' => $maxSeverity ?? 'warning',
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
