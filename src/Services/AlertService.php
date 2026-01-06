<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\DB;

class AlertService
{
    private const SEV_WEIGHT = [
        'ok' => 0,
        'warning' => 1,
        'critical' => 2,
        'severe' => 3,
    ];

    public function deviceAlerts(array $deviceIds): array
    {
        if (empty($deviceIds)) {
            return [];
        }

        $alerts = $this->fetchDeviceAlerts($deviceIds);
        return $this->buildAlertsByDevice($alerts, $deviceIds);
    }

    public function portAlerts(array $portIds): array
    {
        if (empty($portIds)) {
            return [];
        }

        $alerts = $this->fetchPortAlerts($portIds);
        return $this->buildAlertsByPort($alerts, $portIds);
    }

    private function fetchDeviceAlerts(array $deviceIds): array
    {
        $alerts = $this->fetchDeviceAlertsFromTable($deviceIds);

        if (!empty($alerts)) {
            return $alerts;
        }

        return $this->fetchDeviceAlertsFromEntityTable($deviceIds);
    }

    private function fetchDeviceAlertsFromTable(array $deviceIds): array
    {
        try {
            return DB::table('alerts')
                ->select('device_id', 'state', 'severity')
                ->whereIn('device_id', $deviceIds)
                ->where('state', '!=', 0)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchDeviceAlertsFromEntityTable(array $deviceIds): array
    {
        try {
            return DB::table('alerts')
                ->select('entity_id as device_id', 'state', 'severity')
                ->where('entity_type', 'device')
                ->whereIn('entity_id', $deviceIds)
                ->where('state', '!=', 0)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function buildAlertsByDevice(array $alerts, array $deviceIds): array
    {
        $out = [];
        foreach ($deviceIds as $deviceId) {
            $out[$deviceId] = [
                'count' => 0,
                'severity' => 'warning',
            ];
        }

        foreach ($alerts as $alert) {
            $devId = (int) ($alert['device_id'] ?? 0);
            if (!$devId || !isset($out[$devId])) {
                continue;
            }

            $severity = $this->normalizeSeverity($alert['severity'] ?? null);
            $out[$devId]['count']++;
            $out[$devId]['severity'] = $this->maxSeverity($out[$devId]['severity'], $severity);
        }

        return $out;
    }

    private function fetchPortAlerts(array $portIds): array
    {
        $alerts = $this->fetchPortAlertsFromTable($portIds);

        if (!empty($alerts)) {
            return $alerts;
        }

        return $this->fetchPortAlertsFromOperStatus($portIds);
    }

    private function fetchPortAlertsFromTable(array $portIds): array
    {
        try {
            return DB::table('alerts')
                ->select('entity_id as port_id', 'state', 'severity')
                ->where('entity_type', 'port')
                ->whereIn('entity_id', $portIds)
                ->where('state', '!=', 0)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchPortAlertsFromOperStatus(array $portIds): array
    {
        try {
            $ports = DB::table('ports')
                ->select('port_id', 'ifOperStatus')
                ->whereIn('port_id', $portIds)
                ->where('ifOperStatus', 'down')
                ->get()
                ->toArray();

            return array_map(fn($port) => [
                'port_id' => $port['port_id'],
                'severity' => 'critical',
            ], $ports);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function buildAlertsByPort(array $alerts, array $portIds): array
    {
        $out = [];
        foreach ($portIds as $portId) {
            $out[$portId] = [
                'count' => 0,
                'severity' => 'warning',
            ];
        }

        foreach ($alerts as $alert) {
            $pid = (int) ($alert['port_id'] ?? 0);
            if (!$pid || !isset($out[$pid])) {
                continue;
            }

            $severity = $this->normalizeSeverity($alert['severity'] ?? null);
            $out[$pid]['count']++;
            $out[$pid]['severity'] = $this->maxSeverity($out[$pid]['severity'], $severity);
        }

        return $out;
    }

    private function normalizeSeverity($sev): string
    {
        if ($sev === null) {
            return 'warning';
        }

        if (is_numeric($sev)) {
            $numericSeverity = (int) $sev;
            if ($numericSeverity >= 3) {
                return 'severe';
            }
            if ($numericSeverity >= 2) {
                return 'critical';
            }
            if ($numericSeverity >= 1) {
                return 'warning';
            }
            return 'ok';
        }

        $stringSeverity = strtolower((string) $sev);
        return in_array($stringSeverity, ['ok', 'warning', 'critical', 'severe']) ? $stringSeverity : 'warning';
    }

    private function maxSeverity(string $severityA, string $severityB): string
    {
        $weightA = self::SEV_WEIGHT[$severityA] ?? 0;
        $weightB = self::SEV_WEIGHT[$severityB] ?? 0;
        return $weightA >= $weightB ? $severityA : $severityB;
    }
}
