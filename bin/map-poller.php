#!/usr/bin/env php
<?php
/**
 * WeathermapNG Map Poller
 *
 * CLI script to poll and cache map data for improved performance.
 * Run this via cron every 5 minutes.
 */

require __DIR__ . '/../../includes/init.php'; // LibreNMS bootstrap

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