<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\DB;

class SnmpDataService
{
    private $snmpConfig;

    public function __construct()
    {
        $this->snmpConfig = config('weathermapng.snmp', []);
    }

    public function getPortTraffic(int $portId): ?array
    {
        if (!($this->snmpConfig['enabled'] ?? false)) {
            return null;
        }

        if (!function_exists('snmp2_get')) {
            return null;
        }

        $portInfo = $this->getPortInfo($portId);
        $deviceInfo = $this->getDeviceInfo($portInfo['device_id'] ?? null);

        $host = $deviceInfo['hostname'] ?? null;
        $ifIndex = $portInfo['ifIndex'] ?? null;
        $community = $this->snmpConfig['community'] ?? null;

        if (!$host || !$ifIndex || !$community) {
            return null;
        }

        return $this->fetchSnmpTraffic($host, $community, $ifIndex);
    }

    private function getPortInfo(int $portId): ?array
    {
        try {
            $port = class_exists('\\App\\Models\\Port')
                ? \App\Models\Port::select('device_id', 'ifIndex')
                    ->where('port_id', $portId)
                    ->first()
                : DB::table('ports')
                    ->select('device_id', 'ifIndex')
                    ->where('port_id', $portId)
                    ->first();

            return $port ? (array) $port : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getDeviceInfo(?int $deviceId): ?array
    {
        if (!$deviceId) {
            return null;
        }

        try {
            $device = class_exists('\\App\\Models\\Device')
                ? \App\Models\Device::select('hostname')
                    ->where('device_id', $deviceId)
                    ->first()
                : DB::table('devices')
                    ->select('hostname')
                    ->where('device_id', $deviceId)
                    ->first();

            return $device ? (array) $device : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchSnmpTraffic(string $host, string $community, int $ifIndex): ?array
    {
        try {
            $timeout = $this->snmpConfig['timeout'] ?? 1;
            $retries = $this->snmpConfig['retries'] ?? 1;

            $inOid = ".1.3.6.1.2.1.2.2.1.10.{$ifIndex}.1";
            $outOid = ".1.3.6.1.2.1.2.2.1.10.{$ifIndex}.2";

            $inOctets = snmp2_get($host, $community, $inOid, $timeout, $retries);
            $outOctets = snmp2_get($host, $community, $outOid, $timeout, $retries);

            if ($inOctets === false || $outOctets === false) {
                return null;
            }

            return [
                'in' => (int) $inOctets * 8,
                'out' => (int) $outOctets * 8,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
