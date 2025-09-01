<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;
use LibreNMS\Plugins\WeathermapNG\Services\DevicePortLookup;
use LibreNMS\Plugins\WeathermapNG\Services\Logger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthController
{
    private ?Logger $logger = null;
    
    protected function getLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = Logger::getInstance();
        }
        return $this->logger;
    }
    /**
     * Basic health check endpoint (v2)
     * GET /plugin/WeathermapNG/health
     */
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
            $this->getLogger()->error('Health check database failure', ['error' => $e->getMessage()]);
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
    
    /**
     * Readiness probe for container orchestration
     * GET /plugin/WeathermapNG/ready
     */
    public function ready(Request $request)
    {
        try {
            // Check database connectivity
            DB::connection()->getPdo();
            
            // Check critical directories
            $outputDir = config('weathermapng.output_dir', __DIR__ . '/../../../output/maps/');
            if (!is_dir($outputDir)) {
                throw new \Exception('Output directory not found');
            }
            
            return response()->json([
                'ready' => true,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            $this->getLogger()->error('Readiness check failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'ready' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 503);
        }
    }
    
    /**
     * Liveness probe for container orchestration
     * GET /plugin/WeathermapNG/live
     */
    public function live(Request $request)
    {
        return response()->json([
            'alive' => true,
            'timestamp' => now()->toISOString(),
            'pid' => getmypid()
        ]);
    }
    
    /**
     * Prometheus metrics endpoint
     * GET /plugin/WeathermapNG/metrics
     */
    public function metrics(Request $request)
    {
        $metrics = [];
        
        // Database metrics
        try {
            $mapCount = Map::count();
            $nodeCount = DB::table('wmng_nodes')->count();
            $linkCount = DB::table('wmng_links')->count();
            
            $metrics[] = "# HELP weathermapng_maps_total Total number of maps";
            $metrics[] = "# TYPE weathermapng_maps_total gauge";
            $metrics[] = "weathermapng_maps_total $mapCount";
            
            $metrics[] = "# HELP weathermapng_nodes_total Total number of nodes";
            $metrics[] = "# TYPE weathermapng_nodes_total gauge";
            $metrics[] = "weathermapng_nodes_total $nodeCount";
            
            $metrics[] = "# HELP weathermapng_links_total Total number of links";
            $metrics[] = "# TYPE weathermapng_links_total gauge";
            $metrics[] = "weathermapng_links_total $linkCount";
        } catch (\Exception $e) {
            $this->getLogger()->error('Failed to collect metrics', ['error' => $e->getMessage()]);
        }
        
        // Memory metrics
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        
        $metrics[] = "# HELP weathermapng_memory_usage_bytes Current memory usage";
        $metrics[] = "# TYPE weathermapng_memory_usage_bytes gauge";
        $metrics[] = "weathermapng_memory_usage_bytes $memoryUsage";
        
        $metrics[] = "# HELP weathermapng_memory_peak_bytes Peak memory usage";
        $metrics[] = "# TYPE weathermapng_memory_peak_bytes gauge";
        $metrics[] = "weathermapng_memory_peak_bytes $memoryPeak";
        
        return response(implode("\n", $metrics) . "\n")
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }
    
    /**
     * Detailed health check
     * GET /plugin/WeathermapNG/health/detailed
     */
    public function detailed(Request $request)
    {
        $startTime = microtime(true);
        $checks = [];
        
        // Database check
        $checks['database'] = $this->checkDatabase();
        
        // Filesystem check
        $checks['filesystem'] = $this->checkFilesystem();
        
        // Dependencies check
        $checks['dependencies'] = $this->checkDependencies();
        
        // Configuration check
        $checks['configuration'] = $this->checkConfiguration();
        
        // Performance metrics
        $checks['performance'] = $this->getPerformanceMetrics();
        
        // Overall status
        $overallStatus = $this->determineOverallStatus($checks);
        
        $response = [
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'version' => $this->getVersion(),
            'checks' => $checks,
            'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ];
        
        $statusCode = $overallStatus === 'healthy' ? 200 : 503;
        
        if ($overallStatus !== 'healthy') {
            $this->getLogger()->warning('Health check detected issues', $response);
        }
        
        return response()->json($response, $statusCode);
    }
    
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);
            
            // Check tables exist
            $tables = ['wmng_maps', 'wmng_nodes', 'wmng_links'];
            $missingTables = [];
            
            foreach ($tables as $table) {
                if (!\Schema::hasTable($table)) {
                    $missingTables[] = $table;
                }
            }
            
            if (!empty($missingTables)) {
                return [
                    'status' => 'degraded',
                    'message' => 'Missing tables: ' . implode(', ', $missingTables),
                    'response_time_ms' => $responseTime
                ];
            }
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function checkFilesystem(): array
    {
        $checks = [];
        
        // Check output directory
        $outputDir = config('weathermapng.output_dir', __DIR__ . '/../../../output/maps/');
        if (is_dir($outputDir)) {
            $checks['output_directory'] = is_writable($outputDir) ? 'writable' : 'not_writable';
        } else {
            $checks['output_directory'] = 'missing';
        }
        
        // Check config directory
        $configDir = __DIR__ . '/../../../config';
        $checks['config_directory'] = is_dir($configDir) ? 'exists' : 'missing';
        
        // Check log writability
        $logPath = config('logging.output', '/var/log/librenms/weathermapng.log');
        $logDir = dirname($logPath);
        if (is_dir($logDir)) {
            $checks['log_directory'] = is_writable($logDir) ? 'writable' : 'not_writable';
        } else {
            $checks['log_directory'] = 'missing';
        }
        
        $status = in_array('missing', $checks) || in_array('not_writable', $checks) 
            ? 'degraded' 
            : 'healthy';
        
        return [
            'status' => $status,
            'directories' => $checks
        ];
    }
    
    private function checkDependencies(): array
    {
        $checks = [];
        
        // Check PHP extensions
        $requiredExtensions = ['gd', 'json', 'pdo', 'mbstring'];
        $missingExtensions = [];
        
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }
        
        if (!empty($missingExtensions)) {
            $checks['php_extensions'] = [
                'status' => 'missing',
                'missing' => $missingExtensions
            ];
        } else {
            $checks['php_extensions'] = ['status' => 'loaded'];
        }
        
        // Check Composer dependencies
        $vendorDir = __DIR__ . '/../../../vendor';
        $checks['composer'] = is_dir($vendorDir) ? 'installed' : 'missing';
        
        $status = !empty($missingExtensions) || $checks['composer'] === 'missing' 
            ? 'unhealthy' 
            : 'healthy';
        
        return [
            'status' => $status,
            'checks' => $checks
        ];
    }
    
    private function checkConfiguration(): array
    {
        $issues = [];
        
        // Check for .env file
        if (!file_exists(__DIR__ . '/../../../.env')) {
            $issues[] = '.env file missing (using defaults)';
        }
        
        // Check critical config values
        if (empty(config('weathermapng'))) {
            $issues[] = 'WeathermapNG configuration missing';
        }
        
        return [
            'status' => empty($issues) ? 'healthy' : 'degraded',
            'issues' => $issues
        ];
    }
    
    private function getPerformanceMetrics(): array
    {
        return [
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'memory_limit' => ini_get('memory_limit'),
            'load_average' => sys_getloadavg()
        ];
    }
    
    private function determineOverallStatus(array $checks): string
    {
        $unhealthyCount = 0;
        $degradedCount = 0;
        
        foreach ($checks as $check) {
            if (isset($check['status'])) {
                if ($check['status'] === 'unhealthy') {
                    $unhealthyCount++;
                } elseif ($check['status'] === 'degraded') {
                    $degradedCount++;
                }
            }
        }
        
        if ($unhealthyCount > 0) {
            return 'unhealthy';
        } elseif ($degradedCount > 0) {
            return 'degraded';
        }
        
        return 'healthy';
    }
    
    private function getVersion(): string
    {
        $composerJson = __DIR__ . '/../../../composer.json';
        if (file_exists($composerJson)) {
            $composer = json_decode(file_get_contents($composerJson), true);
            return $composer['version'] ?? '1.0.0';
        }
        return '1.0.0';
    }
}
