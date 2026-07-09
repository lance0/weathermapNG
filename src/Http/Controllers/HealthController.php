<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use LibreNMS\Plugins\WeathermapNG\AdminCheck;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;
use LibreNMS\Plugins\WeathermapNG\Services\DevicePortLookup;
use LibreNMS\Plugins\WeathermapNG\Services\Logger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class HealthController
{
    use AdminCheck;

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
    public function check(): \Illuminate\Http\JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => $this->getVersion(),
            'checks' => []
        ];

        // Run all health checks
        $checks = [
            'database' => $this->checkDatabase(),
            'filesystem' => $this->checkFilesystem(),
            'dependencies' => $this->checkDependencies(),
            'configuration' => $this->checkConfiguration(),
        ];

        $health['checks'] = $checks;
        $health['status'] = $this->determineOverallStatus($checks);

        $statusCode = $this->getHttpStatusCode($health['status']);
        return response()->json($health, $statusCode);
    }

    private function getHttpStatusCode(string $status): int
    {
        return match ($status) {
            'healthy' => 200,
            'warning' => 200,
            'unhealthy' => 503,
            default => 200,
        };
    }

    public function stats(): \Illuminate\Http\JsonResponse
    {
        $this->requireAdmin();

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

    private function getDatabaseSize(): string
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

    private function getCacheInfo(): array
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
    public function ready(): \Illuminate\Http\JsonResponse
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
                'error' => 'Readiness check failed',
                'timestamp' => now()->toISOString()
            ], 503);
        }
    }

    /**
     * Liveness probe for container orchestration
     * GET /plugin/WeathermapNG/live
     */
    public function live(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'alive' => true,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Prometheus metrics endpoint
     * GET /plugin/WeathermapNG/metrics
     */
    public function diagnostics(): \Illuminate\View\View
    {
        $this->requireAdmin();

        $health = $this->check()->getData(true);

        $stats = [
            'maps' => 0,
            'nodes' => 0,
            'links' => 0,
            'database_size' => 'Unknown',
        ];
        try {
            $stats['maps'] = Map::count();
            $stats['nodes'] = DB::table('wmng_nodes')->count();
            $stats['links'] = DB::table('wmng_links')->count();
            $stats['database_size'] = $this->getDatabaseSize();
        } catch (\Exception $e) {
            $stats['error'] = 'Unable to query statistics: ' . $e->getMessage();
        }

        $checks = [
            'database' => $this->checkDatabase(),
            'filesystem' => $this->checkFilesystem(),
            'dependencies' => $this->checkDependencies(),
            'configuration' => $this->checkConfiguration(),
            'performance' => $this->getPerformanceMetrics(),
        ];

        $routes = [
            ['name' => 'Index', 'route' => 'weathermapng.index', 'method' => 'GET', 'url' => route('weathermapng.index')],
            ['name' => 'Embed', 'route' => 'weathermapng.embed', 'method' => 'GET'],
            ['name' => 'Map JSON', 'route' => 'weathermapng.json', 'method' => 'GET'],
            ['name' => 'Live data', 'route' => 'weathermapng.live', 'method' => 'GET'],
            ['name' => 'Save map', 'route' => 'weathermapng.map.save', 'method' => 'POST'],
            ['name' => 'Health check', 'route' => 'weathermapng.health', 'method' => 'GET', 'url' => route('weathermapng.health')],
            ['name' => 'Health detailed', 'route' => 'weathermapng.health.detailed', 'method' => 'GET', 'url' => route('weathermapng.health.detailed')],
            ['name' => 'Health stats', 'route' => 'weathermapng.health.stats', 'method' => 'GET', 'url' => route('weathermapng.health.stats')],
            ['name' => 'Metrics', 'route' => 'weathermapng.metrics', 'method' => 'GET', 'url' => route('weathermapng.metrics')],
            ['name' => 'Diagnostics', 'route' => 'weathermapng.diagnostics', 'method' => 'GET', 'url' => route('weathermapng.diagnostics')],
        ];

        $routeStatus = array_map(function ($r) {
            if (!Route::has($r['route'])) {
                $r['url'] = '#';
                $r['status'] = 'missing';
                return $r;
            }
            // Parameterized routes are registered; don't try to synthesize a URL.
            if (!isset($r['url'])) {
                $r['url'] = '#';
            }
            $r['status'] = 'ok';
            return $r;
        }, $routes);

        $writablePaths = [
            'output/maps' => config('weathermapng.output_dir', __DIR__ . '/../../../output/maps/'),
            'resources/output' => __DIR__ . '/../../../resources/output/',
            'storage' => __DIR__ . '/../../../storage/',
        ];

        $pathStatus = [];
        foreach ($writablePaths as $label => $path) {
            $resolved = realpath($path) ?: $path;
            $pathStatus[$label] = [
                'path' => $resolved,
                'exists' => is_dir($resolved),
                'writable' => is_writable($resolved),
            ];
        }

        return view('WeathermapNG::diagnostics', [
            'version' => $this->getVersion(),
            'overallStatus' => $health['status'] ?? 'unknown',
            'checks' => $checks,
            'stats' => $stats,
            'routes' => $routeStatus,
            'paths' => $pathStatus,
            'librenmsVersion' => config('librenms.version', 'unknown'),
        ]);
    }

    public function metrics(): \Illuminate\Http\Response
    {
        $this->requireAdmin();

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
    public function detailed(): \Illuminate\Http\JsonResponse
    {
        $this->requireAdmin();

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
            DB::connection()->getPdo();
            return [
                'status' => 'healthy',
                'message' => 'Database connected'
            ];
        } catch (\Exception $exception) {
            $this->getLogger()->error('Health check database failure', ['error' => $exception->getMessage()]);
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed'
            ];
        }
    }

    private function checkFilesystem(): array
    {
        $checks = [];

        // RRD access check
        $rrdPath = config('weathermapng.rrd_base', '/opt/librenms/rrd');
        $checks['rrd'] = [
            'status' => is_readable($rrdPath) ? 'healthy' : 'warning',
            'message' => is_readable($rrdPath)
                ? 'RRD directory accessible'
                : 'RRD directory not accessible'
        ];

        // Output directory check
        $outputDir = config('weathermapng.output_dir', __DIR__ . '/../../../output/maps/');
        $checks['output'] = [
            'status' => is_writable($outputDir) ? 'healthy' : 'warning',
            'message' => is_writable($outputDir)
                ? 'Output directory writable'
                : 'Output directory not writable'
        ];

        // Return the most severe status
        $overallStatus = 'healthy';
        $messages = [];

        foreach ($checks as $check) {
            $messages[] = $check['message'];
            if ($check['status'] === 'unhealthy') {
                $overallStatus = 'unhealthy';
            } elseif ($check['status'] === 'warning' && $overallStatus === 'healthy') {
                $overallStatus = 'warning';
            }
        }

        return [
            'status' => $overallStatus,
            'message' => implode('; ', $messages)
        ];
    }

    private function checkDependencies(): array
    {
        $missingDeps = [];

        // Check GD extension
        if (!extension_loaded('gd')) {
            $missingDeps[] = 'GD extension';
        }

        // Check JSON extension
        if (!extension_loaded('json')) {
            $missingDeps[] = 'JSON extension';
        }

        if (empty($missingDeps)) {
            return [
                'status' => 'healthy',
                'message' => 'All required PHP extensions loaded'
            ];
        }

        return [
            'status' => 'unhealthy',
            'message' => 'Missing PHP extensions: ' . implode(', ', $missingDeps)
        ];
    }

    private function checkConfiguration(): array
    {
        $issues = [];

        // API token check
        $apiToken = config('weathermapng.api_token');
        if (!$apiToken) {
            $issues[] = 'Configuration incomplete (API fallback may not work)';
        }

        if (empty($issues)) {
            return [
                'status' => 'healthy',
                'message' => 'Configuration is valid'
            ];
        }

        return [
            'status' => 'warning',
            'message' => implode('; ', $issues)
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
        foreach ($checks as $check) {
            if (($check['status'] ?? 'healthy') === 'unhealthy') {
                return 'unhealthy';
            }
        }

        foreach ($checks as $check) {
            if (($check['status'] ?? 'healthy') === 'warning') {
                return 'warning';
            }
        }

        return 'healthy';
    }

    private function getVersion(): string
    {
        $versionFile = __DIR__ . '/../../../VERSION';
        if (is_readable($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        $composerJson = __DIR__ . '/../../../composer.json';
        if (file_exists($composerJson)) {
            $composer = json_decode(file_get_contents($composerJson), true);
            return $composer['version'] ?? '1.0.0';
        }
        return '1.0.0';
    }
}
