#!/usr/bin/env php
<?php
/**
 * WeathermapNG Map Poller
 *
 * CLI script to poll and cache map data for improved performance.
 * Run this via cron every 5 minutes.
 */

// Locate LibreNMS bootstrap
$bootstrapCandidates = [
    // Legacy includes/init.php
    '/opt/librenms/includes/init.php',
    __DIR__ . '/../../../../includes/init.php',
    __DIR__ . '/../../includes/init.php',
    // Laravel bootstrap for LibreNMS v2+
    '/opt/librenms/bootstrap/app.php',
    __DIR__ . '/../../../../bootstrap/app.php',
];

$bootstrapLoaded = false;
foreach ($bootstrapCandidates as $file) {
    if (file_exists($file)) {
        if (substr($file, -13) === 'bootstrap/app.php') {
            // Load vendor autoload
            $vendor = dirname($file) . '/vendor/autoload.php';
            if (file_exists($vendor)) {
                require $vendor;
            }
            $app = require $file;
            $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();
        } else {
            require $file;
        }
        $bootstrapLoaded = true;
        break;
    }
}

if (!$bootstrapLoaded) {
    fwrite(STDERR, "Unable to locate LibreNMS bootstrap (includes/init.php).\n");
    exit(1);
}

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;
use LibreNMS\Plugins\WeathermapNG\Services\DevicePortLookup;

class MapPoller
{
    private $processed = 0;
    private $errors = 0;
    private $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function run()
    {
        $this->log("Starting WeathermapNG poller at " . date('Y-m-d H:i:s'));

        try {
            $maps = Map::with(['nodes', 'links'])->get();

            if ($maps->isEmpty()) {
                $this->log("No maps found to process");
                return;
            }

            foreach ($maps as $map) {
                $this->processMap($map);
            }

            $duration = round(microtime(true) - $this->startTime, 2);
            $this->log("Poller completed in {$duration}s. Processed: {$this->processed}, Errors: {$this->errors}");

        } catch (\Exception $e) {
            $this->log("Critical error: " . $e->getMessage());
            exit(1);
        }
    }

    private function processMap(Map $map)
    {
        try {
            $this->log("Processing map: {$map->name}");

            // Warm up caches by fetching live data
            $svc = new PortUtilService();
            $liveData = $this->getLiveData($map, $svc);

            // Cache the live data (optional - could be stored in Redis/file)
            $this->cacheLiveData($map, $liveData);

            // Generate any static assets if needed
            $this->generateStaticAssets($map);

            $this->processed++;
            $this->log("Successfully processed map: {$map->name}");

        } catch (\Exception $e) {
            $this->errors++;
            $this->log("Error processing map {$map->name}: " . $e->getMessage());
        }
    }

    private function getLiveData(Map $map, PortUtilService $svc): array
    {
        $liveData = [
            'ts' => time(),
            'links' => []
        ];

        foreach ($map->links as $link) {
            $liveData['links'][$link->id] = $svc->linkUtilBits([
                'port_id_a' => $link->port_id_a,
                'port_id_b' => $link->port_id_b,
                'bandwidth_bps' => $link->bandwidth_bps,
            ]);
        }

        return $liveData;
    }

    private function cacheLiveData(Map $map, array $liveData)
    {
        // Cache live data for faster API responses
        $cacheKey = "weathermapng.live.{$map->id}";
        $cacheTtl = config('weathermapng.cache_ttl', 300);

        \Illuminate\Support\Facades\Cache::put($cacheKey, $liveData, $cacheTtl);

        // Optionally write to file for backup
        $cacheFile = __DIR__ . "/../output/cache/{$map->name}.json";
        $this->ensureDirectory(dirname($cacheFile));

        file_put_contents($cacheFile, json_encode($liveData, JSON_PRETTY_PRINT));

        // Calculate and cache 95th percentile for last day if possible
        try {
            $summary = $this->summarizeMap($map);
            if (!empty($summary)) {
                \Illuminate\Support\Facades\Cache::put("weathermapng.summary.{$map->id}", $summary, 3600);
            }
        } catch (\Exception $e) {
            // ignore
        }
    }

    private function summarizeMap(Map $map): array
    {
        $svc = new PortUtilService();
        $sum = ['links' => []];
        foreach ($map->links as $link) {
            $a = $link->port_id_a; $b = $link->port_id_b;
            if (!$a && !$b) continue;
            $histIn = $svc->getPortHistory($a ?: $b, 'traffic_in', '24h');
            $histOut = $svc->getPortHistory($a ?: $b, 'traffic_out', '24h');
            $p95In = $this->percentile($histIn, 95);
            $p95Out = $this->percentile($histOut, 95);
            $sum['links'][$link->id] = [
                'p95_in_bps' => (int) round($p95In * 8),
                'p95_out_bps' => (int) round($p95Out * 8),
            ];
        }
        return $sum;
    }

    private function percentile(array $data, $pct)
    {
        if (empty($data)) return 0;
        $vals = array_map(function($d){ return $d['value'] ?? 0; }, $data);
        sort($vals);
        $rank = max(0, min(count($vals)-1, (int) round(($pct/100) * (count($vals)-1))));
        return $vals[$rank];
    }

    private function generateStaticAssets(Map $map)
    {
        // Generate static thumbnails or pre-rendered images if needed
        // This could be extended to generate PNG thumbnails of maps

        $thumbnailDir = __DIR__ . '/../output/thumbnails';
        $this->ensureDirectory($thumbnailDir);

        // Placeholder for thumbnail generation
        // In a full implementation, this would render a small PNG of the map
    }

    private function ensureDirectory($dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";

        // Log to stdout (captured by cron)
        echo $logMessage;

        // Also log to file if configured
        $logFile = config('weathermapng.log_file', __DIR__ . '/../logs/poller.log');
        if ($logFile) {
            $this->ensureDirectory(dirname($logFile));
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }
}

// Run the poller
$poller = new MapPoller();
$poller->run();

echo "WeathermapNG poller completed successfully.\n";
