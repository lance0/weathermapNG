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
        // numeric severities are also possible; treat higher as worse
    ];

    public function deviceAlerts(array $deviceIds): array
    {
        $out = [];
        if (empty($deviceIds)) {
            return $out;
        }

        try {
            // Attempt modern schema: alerts table with device_id
            $rows = DB::table('alerts')->select('device_id', 'state', 'severity')
                ->whereIn('device_id', $deviceIds)
                ->where('state', '!=', 0)
                ->get();
            foreach ($rows as $r) {
                $devId = (int) ($r->device_id ?? 0);
                if (!$devId) {
                    continue;
                }
                $sev = $this->normalizeSeverity($r->severity ?? null);
                if (!isset($out[$devId])) {
                    $out[$devId] = ['count' => 0, 'severity' => 'warning'];
                }
                $out[$devId]['count']++;
                $out[$devId]['severity'] = $this->maxSeverity($out[$devId]['severity'], $sev);
            }
            return $out;
        } catch (\Throwable $e) {
            // Fallback: entity-based schema
        }

        try {
            $rows = DB::table('alerts')->select('entity_id', 'state', 'severity')
                ->where('entity_type', 'device')
                ->whereIn('entity_id', $deviceIds)
                ->where('state', '!=', 0)
                ->get();
            foreach ($rows as $r) {
                $devId = (int) ($r->entity_id ?? 0);
                if (!$devId) {
                    continue;
                }
                $sev = $this->normalizeSeverity($r->severity ?? null);
                if (!isset($out[$devId])) {
                    $out[$devId] = ['count' => 0, 'severity' => 'warning'];
                }
                $out[$devId]['count']++;
                $out[$devId]['severity'] = $this->maxSeverity($out[$devId]['severity'], $sev);
            }
        } catch (\Throwable $e) {
            // ignore, return whatever we have
        }

        return $out;
    }

    public function portAlerts(array $portIds): array
    {
        $out = [];
        if (empty($portIds)) {
            return $out;
        }

        try {
            // entity-based schema is most common for ports
            $rows = DB::table('alerts')->select('entity_id', 'state', 'severity')
                ->where('entity_type', 'port')
                ->whereIn('entity_id', $portIds)
                ->where('state', '!=', 0)
                ->get();
            foreach ($rows as $r) {
                $pid = (int) ($r->entity_id ?? 0);
                if (!$pid) {
                    continue;
                }
                $sev = $this->normalizeSeverity($r->severity ?? null);
                if (!isset($out[$pid])) {
                    $out[$pid] = ['count' => 0, 'severity' => 'warning'];
                }
                $out[$pid]['count']++;
                $out[$pid]['severity'] = $this->maxSeverity($out[$pid]['severity'], $sev);
            }
            return $out;
        } catch (\Throwable $e) {
            // Fallback: detect oper status down (heuristic)
        }

        try {
            $rows = DB::table('ports')->select('port_id', 'ifOperStatus')
                ->whereIn('port_id', $portIds)
                ->where('ifOperStatus', 'down')
                ->get();
            foreach ($rows as $r) {
                $pid = (int) ($r->port_id ?? 0);
                if (!$pid) {
                    continue;
                }
                $out[$pid] = ['count' => 1, 'severity' => 'critical'];
            }
        } catch (\Throwable $e) {
            // ignore
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
