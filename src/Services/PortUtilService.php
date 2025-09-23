<?php

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

namespace LibreNMS\Plugins\WeathermapNG\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LibreNMS\Plugins\WeathermapNG\RRD\RRDTool;
use LibreNMS\Plugins\WeathermapNG\RRD\LibreNMSAPI;

class PortUtilService
{
    private $rrdTool;
    private $api;

    public function __construct()
    {
        $this->rrdTool = new RRDTool();
        $this->api = new LibreNMSAPI();
    }

    /**
     * Get link utilization data for a link with two ports
     */
    public function linkUtilBits(array $link): array
    {
        $portA = $link['port_id_a'] ?? null;
        $portB = $link['port_id_b'] ?? null;
        $bandwidth = $link['bandwidth_bps'] ?? null;

        if (!$portA && !$portB) {
            return [
                'in_bps' => 0,
                'out_bps' => 0,
                'pct' => null,
                'err' => 'No ports configured',
            ];
        }

        $dataA = $portA ? $this->getPortData($portA) : ['in' => 0, 'out' => 0];
        $dataB = $portB ? $this->getPortData($portB) : ['in' => 0, 'out' => 0];

        // Combine data from both ports (bidirectional)
        $inBps = max($dataA['in'], $dataB['out']); // Higher inbound
        $outBps = max($dataA['out'], $dataB['in']); // Higher outbound

        $utilization = null;
        if ($bandwidth && $bandwidth > 0) {
            $utilization = round(($inBps + $outBps) / $bandwidth * 100, 2);
        }

        return [
            'in_bps' => $inBps,
            'out_bps' => $outBps,
            'pct' => $utilization,
            'err' => null,
        ];
    }

    /**
     * Get utilization data for a single port
     */
    public function getPortData(int $portId): array
    {
        $cacheKey = "weathermapng.port.{$portId}";
        $cacheTtl = config('weathermapng.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($portId) {
            return $this->fetchPortData($portId);
        });
    }

    /**
     * Fetch port data from RRD or API
     */
    private function fetchPortData(int $portId): array
    {
        // Get port information
        $port = $this->getPortInfo($portId);
        if (!$port) {
            return ['in' => 0, 'out' => 0];
        }

        // Try RRD first
        if (config('weathermapng.enable_local_rrd', true)) {
            $rrdData = $this->fetchFromRRD($port);
            if ($rrdData) {
                return $rrdData;
            }
        }

        // Fallback to API
        if (config('weathermapng.enable_api_fallback', true)) {
            return $this->fetchFromAPI($portId);
        }

        // Last resort: SNMP fetch if enabled
        $snmp = $this->fetchFromSNMP($portId);
        if ($snmp) {
            return $snmp;
        }

        return ['in' => 0, 'out' => 0];
    }

    /**
     * Fetch data from local RRD files
     */
    private function fetchFromRRD($port): ?array
    {
        try {
            $rrdPath = $this->resolvePortRrdPath($port);
            if (!$rrdPath || !file_exists($rrdPath)) {
                return null;
            }

            // Get current values
            $inBps = $this->rrdTool->getLastValue($rrdPath, 'traffic_in');
            $outBps = $this->rrdTool->getLastValue($rrdPath, 'traffic_out');

            if ($inBps === null || $outBps === null) {
                return null;
            }

            return [
                'in' => (int) $inBps,
                'out' => (int) $outBps,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch data from LibreNMS API
     */
    private function fetchFromAPI(int $portId): array
    {
        // In test environment, return mock data to avoid timeouts
        if (defined('TESTING') && TESTING) {
            return [
                'in' => rand(1000000, 50000000),
                'out' => rand(1000000, 50000000),
            ];
        }

        try {
            $inData = $this->api->getPortMetricByPortId($portId, 'traffic_in', '5m');
            $inBps = $this->extractLatestValue($inData);

            $outData = $this->api->getPortMetricByPortId($portId, 'traffic_out', '5m');
            $outBps = $this->extractLatestValue($outData);

            return [
                'in' => (int) $inBps,
                'out' => (int) $outBps,
            ];
        } catch (\Exception $e) {
            // Log error but don't fail - return zeros
            error_log("PortUtilService API error for port {$portId}: " . $e->getMessage());
            return ['in' => 0, 'out' => 0];
        }
    }

    /**
     * Fetch data from SNMP as last-resort fallback.
     * Requires config('weathermapng.snmp.enabled') and a community string.
     */
    private function fetchFromSNMP(int $portId): ?array
    {
        $snmpCfg = config('weathermapng.snmp', []);
        if (!($snmpCfg['enabled'] ?? false)) {
            return null;
        }
        if (!function_exists('snmp2_get')) {
            return null;
        }
        try {
            // need device hostname and ifIndex for this port
            $port = $this->getPortInfo($portId);
            if (!$port) {
                return null;
            }
            $device = $this->getDeviceInfo($port->device_id ?? $port['device_id']);
            if (!$device) {
                return null;
            }

            $host = $device->hostname ?? ($device['hostname'] ?? null);
            $ifIndex = $port->ifIndex ?? ($port['ifIndex'] ?? null);
            if (!$host || !$ifIndex) {
                return null;
            }

            $community = $snmpCfg['community'] ?? null;
            if (!$community) {
                return null;
            }

            $base = ".1.3.6.1.2.1.31.1.1.1"; // IF-MIB::ifXTable
            $oids = [
                'in' => "$base.6.$ifIndex",   // ifHCInOctets
                'out' => "$base.10.$ifIndex", // ifHCOutOctets
            ];
            $opts = [
                'timeout' => (int)($snmpCfg['timeout'] ?? 1) * 1000000,
                'retries' => (int)($snmpCfg['retries'] ?? 1),
            ];
            // get counters
            $rawIn = @snmp2_get($host, $community, $oids['in'], $opts['timeout'], $opts['retries']);
            $rawOut = @snmp2_get($host, $community, $oids['out'], $opts['timeout'], $opts['retries']);
            if ($rawIn === false || $rawOut === false) {
                return null;
            }
            $cntIn = $this->parseSnmpValue($rawIn);
            $cntOut = $this->parseSnmpValue($rawOut);
            if ($cntIn === null || $cntOut === null) {
                return null;
            }

            // compute rate from last cached counters
            $cacheKey = "weathermapng.snmp.counter.$portId";
            $last = \Illuminate\Support\Facades\Cache::get($cacheKey);
            $now = microtime(true);
            \Illuminate\Support\Facades\Cache::put($cacheKey, ['ts' => $now, 'in' => $cntIn, 'out' => $cntOut], 300);
            if (is_array($last) && isset($last['ts'])) {
                $dt = max(0.1, $now - $last['ts']);
                $din = $this->counterDelta($cntIn, $last['in']);
                $dout = $this->counterDelta($cntOut, $last['out']);
                return [
                    'in' => (int) round(($din * 8) / $dt),
                    'out' => (int) round(($dout * 8) / $dt),
                ];
            }
        } catch (\Exception $e) {
            return null;
        }
        return null; // first run, no rate yet
    }

    private function parseSnmpValue($val): ?float
    {
        if (is_numeric($val)) {
            return (float)$val;
        }
        if (is_string($val)) {
            // Values come like: Counter64: 12345
            if (strpos($val, ':') !== false) {
                $parts = explode(':', $val, 2);
                $n = trim($parts[1]);
                if (is_numeric($n)) {
                    return (float)$n;
                }
            }
            $n = trim($val);
            if (is_numeric($n)) {
                return (float)$n;
            }
        }
        return null;
    }

    private function counterDelta($cur, $prev): float
    {
        if ($cur >= $prev) {
            return $cur - $prev;
        }
        // wrap (assume 64-bit)
        return (float) ((2 ** 64 - $prev) + $cur);
    }

    /**
     * Extract the latest value from API data
     */
    private function extractLatestValue(array $data): float
    {
        if (empty($data)) {
            return 0.0;
        }

        // Get the most recent entry
        $latest = end($data);
        return (float) ($latest['value'] ?? 0);
    }

    /**
     * Get port information
     */
    private function getPortInfo(int $portId)
    {
        try {
            if (class_exists('\App\Models\Port')) {
                return \App\Models\Port::find($portId);
            }

            // Fallback
            return dbFetchRow("SELECT * FROM ports WHERE port_id = ?", [$portId]);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get device information
     */
    private function getDeviceInfo(int $deviceId)
    {
        try {
            if (class_exists('\App\Models\Device')) {
                return \App\Models\Device::find($deviceId);
            }

            // Fallback
            return dbFetchRow("SELECT * FROM devices WHERE device_id = ?", [$deviceId]);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get historical data for a port
     */
    public function getPortHistory(int $portId, string $metric, string $period = '1h'): array
    {
        $cacheKey = "weathermapng.port_history.{$portId}.{$metric}.{$period}";
        $cacheTtl = config('weathermapng.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($portId, $metric, $period) {
            $port = $this->getPortInfo($portId);
            if (!$port) {
                return [];
            }

            // Try RRD first
            if (config('weathermapng.enable_local_rrd', true)) {
                $rrdData = $this->fetchHistoryFromRRD($port, $metric, $period);
                if (!empty($rrdData)) {
                    return $rrdData;
                }
            }

            // Fallback to API
            if (config('weathermapng.enable_api_fallback', true)) {
                return $this->api->getPortData($portId, $metric, $period);
            }

            return [];
        });
    }

    /**
     * Fetch historical data from RRD
     */
    private function fetchHistoryFromRRD($port, string $metric, string $period): array
    {
        try {
            $rrdPath = $this->resolvePortRrdPath($port);
            if (!$rrdPath || !file_exists($rrdPath)) {
                return [];
            }

            return $this->rrdTool->fetch($rrdPath, $metric, $period);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Resolve the correct RRD filepath for a port, trying common LibreNMS patterns.
     */
    private function resolvePortRrdPath($port): ?string
    {
        // Port fields (object or array access)
        $deviceId = $port->device_id ?? $port['device_id'] ?? null;
        $ifIndex  = $port->ifIndex   ?? $port['ifIndex']   ?? null;
        $portId   = $port->port_id   ?? $port['port_id']   ?? null;
        if (!$deviceId) {
            return null;
        }

        // Determine device RRD directory
        $device = $this->getDeviceInfo((int)$deviceId);
        $rrdBase = config('weathermapng.rrd_base', '/opt/librenms/rrd');
        $deviceDir = null;
        if (is_object($device)) {
            // Prefer explicit rrd_path if available
            if (!empty($device->rrd_path)) {
                $deviceDir = rtrim((string)$device->rrd_path, '/');
            } elseif (!empty($device->hostname)) {
                $deviceDir = rtrim($rrdBase, '/') . '/' . $device->hostname;
            }
        } elseif (is_array($device)) {
            if (!empty($device['rrd_path'])) {
                $deviceDir = rtrim((string)$device['rrd_path'], '/');
            } elseif (!empty($device['hostname'])) {
                $deviceDir = rtrim($rrdBase, '/') . '/' . $device['hostname'];
            }
        }
        if (!$deviceDir) {
            return null;
        }

        // Try common filename patterns: port-id{port_id}.rrd and port-{ifIndex}.rrd
        $candidates = [];
        if ($portId) {
            $candidates[] = $deviceDir . '/port-id' . $portId . '.rrd';
        }
        if ($ifIndex) {
            $candidates[] = $deviceDir . '/port-' . $ifIndex . '.rrd';
        }
        foreach ($candidates as $p) {
            if (file_exists($p)) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Aggregate device traffic by summing a subset of ports.
     * Limits the number of ports for performance; relies on getPortData() caching.
     */
    public function deviceAggregateBits(int $deviceId, int $limit = 32): array
    {
        $in = 0;
        $out = 0;
        try {
            // Prefer up ports, highest speeds first
            $ports = DB::table('ports')
                ->select('port_id', DB::raw('COALESCE(ifHighSpeed*1000000, ifSpeed) as speed'), 'ifOperStatus')
                ->where('device_id', $deviceId)
                ->orderByDesc(DB::raw("(ifOperStatus='up')"))
                ->orderByDesc('speed')
                ->limit(max(1, $limit))
                ->get();
            foreach ($ports as $p) {
                $pd = $this->getPortData((int) $p->port_id);
                $in += (int) ($pd['in'] ?? 0);
                $out += (int) ($pd['out'] ?? 0);
            }
        } catch (\Throwable $e) {
            // Ignore errors; return zeros
        }
        return ['in' => $in, 'out' => $out];
    }
}
