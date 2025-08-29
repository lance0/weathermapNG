<?php
/**
 * WeathermapNG Map Poller
 *
 * This script runs periodically to update all weathermap configurations
 * and generate new map images with current data.
 */

// Include LibreNMS bootstrap
require_once __DIR__ . '/../../includes/bootstrap.php';

// Include WeathermapNG classes
require_once __DIR__ . '/lib/Map.php';
require_once __DIR__ . '/lib/Node.php';
require_once __DIR__ . '/lib/Link.php';
require_once __DIR__ . '/lib/DataSource.php';

use LibreNMS\Plugins\WeathermapNG\Map;
use LibreNMS\Plugins\WeathermapNG\DataSource;

class MapPoller
{
    private $configDir;
    private $outputDir;
    private $thumbnailDir;
    private $logFile;
    private $processed = 0;
    private $errors = 0;

    public function __construct()
    {
        $this->configDir = __DIR__ . '/config/maps/';
        $this->outputDir = __DIR__ . '/output/maps/';
        $this->thumbnailDir = __DIR__ . '/output/thumbnails/';
        $this->logFile = __DIR__ . '/logs/poller.log';

        // Ensure output directories exist
        $this->ensureDirectories();
    }

    private function ensureDirectories()
    {
        $dirs = [$this->outputDir, $this->thumbnailDir, dirname($this->logFile)];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    public function run()
    {
        $this->log("Starting WeathermapNG poller at " . date('Y-m-d H:i:s'));

        $configFiles = glob($this->configDir . '*.conf');

        if (empty($configFiles)) {
            $this->log("No map configuration files found in {$this->configDir}");
            return;
        }

        foreach ($configFiles as $configFile) {
            $this->processMap($configFile);
        }

        $this->log("Poller completed. Processed: {$this->processed}, Errors: {$this->errors}");
    }

    private function processMap($configFile)
    {
        $mapId = basename($configFile, '.conf');
        $outputFile = $this->outputDir . $mapId . '.png';
        $thumbnailFile = $this->thumbnailDir . $mapId . '_thumb.png';

        try {
            $this->log("Processing map: {$mapId}");

            // Load and process map
            $map = new Map($configFile);

            if (!$map->getId()) {
                throw new Exception("Failed to load map configuration");
            }

            // Load real-time data
            $map->loadData();

            // Generate full-size image
            $this->generateImage($map, $outputFile);

            // Generate thumbnail
            $this->generateThumbnail($outputFile, $thumbnailFile);

            $this->processed++;
            $this->log("Successfully processed map: {$mapId}");

        } catch (Exception $e) {
            $this->errors++;
            $this->log("Error processing map {$mapId}: " . $e->getMessage());
        }
    }

    private function generateImage(Map $map, $outputPath)
    {
        // For now, we'll create a simple placeholder image
        // In a full implementation, this would use a proper image rendering library
        $this->createPlaceholderImage($map, $outputPath);
    }

    private function createPlaceholderImage(Map $map, $outputPath)
    {
        $width = $map->getWidth();
        $height = $map->getHeight();

        // Create image using GD if available, otherwise skip
        if (!function_exists('imagecreatetruecolor')) {
            $this->log("GD library not available, skipping image generation for {$outputPath}");
            return;
        }

        $image = imagecreatetruecolor($width, $height);

        // Colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $blue = imagecolorallocate($image, 0, 123, 255);
        $green = imagecolorallocate($image, 40, 167, 69);
        $red = imagecolorallocate($image, 220, 53, 69);

        // Fill background
        imagefill($image, 0, 0, $white);

        // Draw title
        $fontSize = 5;
        $title = $map->getTitle();
        $titleX = ($width - strlen($title) * imagefontwidth($fontSize)) / 2;
        imagestring($image, $fontSize, $titleX, 20, $title, $black);

        // Draw nodes
        foreach ($map->getNodes() as $node) {
            $x = $node->getPosition()['x'];
            $y = $node->getPosition()['y'];

            // Node circle
            $color = $node->getStatus() === 'up' ? $green : $red;
            imagefilledellipse($image, $x, $y, 20, 20, $color);
            imageellipse($image, $x, $y, 20, 20, $black);

            // Node label
            $label = substr($node->getLabel(), 0, 10);
            $labelX = $x - strlen($label) * 2;
            $labelY = $y - 15;
            imagestring($image, 2, $labelX, $labelY, $label, $black);
        }

        // Draw links
        foreach ($map->getLinks() as $link) {
            $sourcePos = $link->getSourceNode()->getPosition();
            $targetPos = $link->getTargetNode()->getPosition();

            $color = $link->getStatus() === 'up' ? $blue : $red;
            imageline($image, $sourcePos['x'], $sourcePos['y'],
                     $targetPos['x'], $targetPos['y'], $color);
        }

        // Add timestamp
        $timestamp = date('Y-m-d H:i:s');
        imagestring($image, 2, 10, $height - 20, "Generated: {$timestamp}", $black);

        // Save image
        imagepng($image, $outputPath);
        imagedestroy($image);
    }

    private function generateThumbnail($sourcePath, $thumbnailPath)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }

        if (!file_exists($sourcePath)) {
            return;
        }

        $sourceImage = imagecreatefrompng($sourcePath);
        if (!$sourceImage) {
            return;
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        $thumbnailSize = 150;
        $thumbnailImage = imagecreatetruecolor($thumbnailSize, $thumbnailSize);

        // Calculate aspect ratio
        $aspectRatio = $sourceWidth / $sourceHeight;
        if ($aspectRatio > 1) {
            $newWidth = $thumbnailSize;
            $newHeight = $thumbnailSize / $aspectRatio;
        } else {
            $newWidth = $thumbnailSize * $aspectRatio;
            $newHeight = $thumbnailSize;
        }

        // Center the image
        $offsetX = ($thumbnailSize - $newWidth) / 2;
        $offsetY = ($thumbnailSize - $newHeight) / 2;

        imagecopyresampled($thumbnailImage, $sourceImage,
                          $offsetX, $offsetY, 0, 0,
                          $newWidth, $newHeight, $sourceWidth, $sourceHeight);

        imagepng($thumbnailImage, $thumbnailPath);
        imagedestroy($sourceImage);
        imagedestroy($thumbnailImage);
    }

    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";

        // Log to file
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // Also log to console if running from command line
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
}

// Run the poller
try {
    $poller = new MapPoller();
    $poller->run();
} catch (Exception $e) {
    error_log("WeathermapNG Poller Error: " . $e->getMessage());
    exit(1);
}

echo "WeathermapNG poller completed successfully.\n";
?>