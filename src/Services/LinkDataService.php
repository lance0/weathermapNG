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
        // Collect all port IDs across all links in one pass, fetch alerts
        // in a single query, then compute per-link counts from the map.
        $allPortIds = [];
        foreach ($map->links as $link) {
            if ($link->port_id_a) {
                $allPortIds[] = $link->port_id_a;
            }
            if ($link->port_id_b) {
                $allPortIds[] = $link->port_id_b;
            }
        }

        $portAlerts = $this->alertService->portAlerts(array_values(array_unique($allPortIds)));

        $linkAlerts = [];
        foreach ($map->links as $link) {
            $alertInfo = $this->calculateLinkAlertInfo($link, $portAlerts);
            if (!empty($alertInfo)) {
                $linkAlerts[$link->id] = array_merge($this->getLinkBaseData(), $alertInfo);
            }
        }
        return $linkAlerts;
    }


    private function getLinkBaseData(): array
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
