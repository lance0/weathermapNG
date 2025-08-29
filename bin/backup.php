#!/usr/bin/env php
<?php
/**
 * WeathermapNG Backup Utility
 *
 * Creates backups of all map data for migration or disaster recovery.
 */

require __DIR__ . '/../../includes/init.php';

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;

class BackupUtility
{
    private $backupDir;
    private $timestamp;

    public function __construct()
    {
        $this->backupDir = __DIR__ . '/../backups';
        $this->timestamp = date('Y-m-d_H-i-s');
        $this->ensureBackupDirectory();
    }

    public function createBackup()
    {
        $this->log("Starting WeathermapNG backup at " . date('Y-m-d H:i:s'));

        $backupData = [
            'metadata' => [
                'version' => '1.0.0',
                'timestamp' => $this->timestamp,
                'maps_count' => Map::count(),
                'nodes_count' => Node::count(),
                'links_count' => Link::count(),
            ],
            'maps' => []
        ];

        $maps = Map::with(['nodes', 'links'])->get();

        foreach ($maps as $map) {
            $backupData['maps'][] = [
                'id' => $map->id,
                'name' => $map->name,
                'title' => $map->title,
                'options' => $map->options,
                'nodes' => $map->nodes->toArray(),
                'links' => $map->links->toArray(),
                'created_at' => $map->created_at,
                'updated_at' => $map->updated_at,
            ];
        }

        $filename = "weathermapng_backup_{$this->timestamp}.json";
        $filepath = $this->backupDir . '/' . $filename;

        file_put_contents($filepath, json_encode($backupData, JSON_PRETTY_PRINT));

        $this->log("Backup created: {$filename}");
        $this->log("Maps backed up: " . count($backupData['maps']));

        return $filepath;
    }

    public function listBackups()
    {
        $backups = glob($this->backupDir . '/weathermapng_backup_*.json');

        if (empty($backups)) {
            $this->log("No backups found");
            return [];
        }

        $backupInfo = [];
        foreach ($backups as $backup) {
            $filename = basename($backup);
            $timestamp = str_replace(['weathermapng_backup_', '.json'], '', $filename);
            $size = filesize($backup);

            $backupInfo[] = [
                'filename' => $filename,
                'timestamp' => $timestamp,
                'size' => $this->formatBytes($size),
                'path' => $backup
            ];
        }

        // Sort by timestamp (newest first)
        usort($backupInfo, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return $backupInfo;
    }

    public function restoreBackup($filename)
    {
        $filepath = $this->backupDir . '/' . $filename;

        if (!file_exists($filepath)) {
            throw new \Exception("Backup file not found: {$filename}");
        }

        $this->log("Starting restore from: {$filename}");

        $backupData = json_decode(file_get_contents($filepath), true);

        if (!$backupData || !isset($backupData['maps'])) {
            throw new \Exception("Invalid backup file format");
        }

        // Disable foreign key checks for clean restore
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // Clear existing data
            Link::truncate();
            Node::truncate();
            Map::truncate();

            // Restore maps
            foreach ($backupData['maps'] as $mapData) {
                $map = Map::create([
                    'name' => $mapData['name'],
                    'title' => $mapData['title'],
                    'options' => $mapData['options'] ?? [],
                ]);

                // Restore nodes
                foreach ($mapData['nodes'] as $nodeData) {
                    Node::create(array_merge($nodeData, ['map_id' => $map->id]));
                }

                // Restore links
                foreach ($mapData['links'] as $linkData) {
                    Link::create(array_merge($linkData, ['map_id' => $map->id]));
                }
            }

            $this->log("Restore completed successfully");
            $this->log("Maps restored: " . count($backupData['maps']));

        } finally {
            \DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function cleanup($days = 30)
    {
        $backups = $this->listBackups();
        $deleted = 0;

        foreach ($backups as $backup) {
            $age = time() - strtotime($backup['timestamp']);
            if ($age > ($days * 24 * 60 * 60)) {
                unlink($backup['path']);
                $deleted++;
                $this->log("Deleted old backup: {$backup['filename']}");
            }
        }

        $this->log("Cleanup completed. Deleted {$deleted} old backups.");
    }

    private function ensureBackupDirectory()
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";

        echo $logMessage;

        // Also log to file if configured
        $logFile = __DIR__ . '/../logs/backup.log';
        if ($logFile) {
            $this->ensureBackupDirectory();
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }
}

// CLI interface
if ($argc < 2) {
    echo "WeathermapNG Backup Utility\n\n";
    echo "Usage:\n";
    echo "  php backup.php create                    - Create new backup\n";
    echo "  php backup.php list                      - List all backups\n";
    echo "  php backup.php restore <filename>        - Restore from backup\n";
    echo "  php backup.php cleanup [days]            - Delete backups older than X days (default: 30)\n";
    exit(1);
}

$utility = new BackupUtility();
$command = $argv[1];

try {
    switch ($command) {
        case 'create':
            $filepath = $utility->createBackup();
            echo "Backup created: {$filepath}\n";
            break;

        case 'list':
            $backups = $utility->listBackups();
            if (empty($backups)) {
                echo "No backups found.\n";
            } else {
                echo "Available backups:\n";
                foreach ($backups as $backup) {
                    echo "  {$backup['filename']} - {$backup['size']} - {$backup['timestamp']}\n";
                }
            }
            break;

        case 'restore':
            if (!isset($argv[2])) {
                echo "Error: Please specify backup filename to restore.\n";
                exit(1);
            }
            $utility->restoreBackup($argv[2]);
            echo "Restore completed successfully.\n";
            break;

        case 'cleanup':
            $days = isset($argv[2]) ? (int)$argv[2] : 30;
            $utility->cleanup($days);
            break;

        default:
            echo "Error: Unknown command '{$command}'\n";
            exit(1);
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Operation completed.\n";