<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            Log::warning("SNMP fallback enabled but snmp2_get() function is missing");
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
            $timeout = (int) ($this->snmpConfig['timeout'] ?? 1) * 1000000;
            $retries = (int) ($this->snmpConfig['retries'] ?? 1);

            $inOid = ".1.3.6.1.2.1.2.2.1.10.{$ifIndex}";
            $outOid = ".1.3.6.1.2.1.2.2.1.16.{$ifIndex}";

            $inOctets = @snmp2_get($host, $community, $inOid, $timeout, $retries);
            $outOctets = @snmp2_get($host, $community, $outOid, $timeout, $retries);

            if ($inOctets === false || $outOctets === false) {
                return null;
            }

            // Simple 32-bit counter octets to bits conversion
            return [
                'in' => (int) $this->cleanSnmpValue($inOctets) * 8,
                'out' => (int) $this->cleanSnmpValue($outOctets) * 8,
            ];
        } catch (\Exception $e) {
            Log::debug("SNMP fetch failed for {$host}: " . $e->getMessage());
            return null;
        }
    }

    private function cleanSnmpValue($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        // Handle string formatted values like "Counter32: 12345"
        if (preg_match('/(\d+)/', (string) $value, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
