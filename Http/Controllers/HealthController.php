<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;
use LibreNMS\Plugins\WeathermapNG\Services\DevicePortLookup;
use Illuminate\Http\Request;

class HealthController
{
    public function check(Request $request)
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'checks' => []
        ];

        // Database connectivity check
        try {
            $mapCount = Map::count();
            $health['checks']['database'] = [
                'status' => 'healthy',
                'message' => "Database connected, {$mapCount} maps found"
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['database'] = [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }

        // RRD access check
        try {
            $rrdPath = config('weathermapng.rrd_base', '/opt/librenms/rrd');
            $health['checks']['rrd'] = [
                'status' => is_readable($rrdPath) ? 'healthy' : 'warning',
                'message' => is_readable($rrdPath)
                    ? 'RRD directory accessible'
                    : 'RRD directory not accessible'
            ];
        } catch (\Exception $e) {
            $health['checks']['rrd'] = [
                'status' => 'warning',
                'message' => 'RRD check failed: ' . $e->getMessage()
            ];
        }

        // Output directory check
        try {
            $outputDir = config('weathermapng.output_dir', __DIR__ . '/../../../output/maps/');
            $health['checks']['output'] = [
                'status' => is_writable($outputDir) ? 'healthy' : 'warning',
                'message' => is_writable($outputDir)
                    ? 'Output directory writable'
                    : 'Output directory not writable'
            ];
        } catch (\Exception $e) {
            $health['checks']['output'] = [
                'status' => 'warning',
                'message' => 'Output directory check failed: ' . $e->getMessage()
            ];
        }

        // API token check
        $apiToken = config('weathermapng.api_token');
        $health['checks']['api_token'] = [
            'status' => $apiToken ? 'healthy' : 'warning',
            'message' => $apiToken
                ? 'API token configured'
                : 'API token not configured (API fallback may not work)'
        ];

        // Determine overall status
        foreach ($health['checks'] as $check) {
            if ($check['status'] === 'unhealthy') {
                $health['status'] = 'unhealthy';
                break;
            } elseif ($check['status'] === 'warning' && $health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }

        $statusCode = $health['status'] === 'healthy' ? 200 :
                     ($health['status'] === 'warning' ? 200 : 503);

        return response()->json($health, $statusCode);
    }

    public function stats(Request $request)
    {
        $stats = [
            'maps' => Map::count(),
            'nodes' => \DB::table('wmng_nodes')->count(),
            'links' => \DB::table('wmng_links')->count(),
            'last_updated' => Map::max('updated_at'),
            'database_size' => $this->getDatabaseSize(),
            'cache_info' => $this->getCacheInfo()
        ];

        return response()->json($stats);
    }

    private function getDatabaseSize()
    {
        try {
            $tables = ['wmng_maps', 'wmng_nodes', 'wmng_links'];
            $totalSize = 0;

            foreach ($tables as $table) {
                $size = \DB::select("
                    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE()
                    AND table_name = ?
                ", [$table]);

                if (!empty($size)) {
                    $totalSize += $size[0]->size_mb ?? 0;
                }
            }

            return round($totalSize, 2) . ' MB';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function getCacheInfo()
    {
        try {
            $cache = app('cache');
            $store = $cache->getStore();

            if (method_exists($store, 'getCache')) {
                // Redis/File cache
                return [
                    'driver' => config('cache.default'),
                    'status' => 'available'
                ];
            }

            return [
                'driver' => config('cache.default'),
                'status' => 'unknown'
            ];
        } catch (\Exception $e) {
            return [
                'driver' => config('cache.default'),
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
