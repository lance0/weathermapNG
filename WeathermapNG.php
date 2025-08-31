<?php
// WeathermapNG.php
namespace LibreNMS\Plugins;

use LibreNMS\Interfaces\Plugin;
use LibreNMS\Plugins\Hooks\Menu;

class WeathermapNG implements Plugin
{
    public $name = 'WeathermapNG';
    public $description = 'Modern interactive network weathermap for LibreNMS';

    public function __construct()
    {
        // Detect Docker environment
        $this->docker_mode = $this->detectDocker();

        // Register service provider if available
        if (class_exists('\Illuminate\Foundation\Application')) {
            $this->registerServiceProvider();
        }

        // Fallback: load routes directly
        if (file_exists(__DIR__ . '/routes.php')) {
            require __DIR__ . '/routes.php';
        }

        Menu::add('WeathermapNG', url('/plugins/weathermapng'));
    }

    private function registerServiceProvider()
    {
        $provider = new WeathermapNGServiceProvider(app());
        $provider->register();
        $provider->boot();
    }

    public function activate()
    {
        // Check requirements first
        $issues = $this->checkRequirements();
        if (!empty($issues)) {
            throw new Exception("Installation requirements not met: " . implode(', ', $issues));
        }

        // Run migrations automatically
        $this->runMigrations();

        // Set up permissions
        $this->setPermissions();

        // Create default config
        $this->createDefaultConfig();

        return true;
    }

    public function deactivate()
    {
        // Plugin deactivation logic
        return true;
    }

    public function uninstall()
    {
        // Plugin uninstall logic
        // Could drop tables, clean up files, etc.
        return true;
    }

    private function runMigrations()
    {
        $migrationPath = __DIR__ . '/database/migrations/';
        $files = glob($migrationPath . '*.php');

        foreach ($files as $file) {
            $migration = require $file;
            try {
                $migration->up();
                $this->log("Migration executed: " . basename($file));
            } catch (Exception $e) {
                $this->log("Migration failed: " . $e->getMessage());
                throw $e;
            }
        }
    }

    private function checkRequirements()
    {
        $issues = [];

        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $issues[] = 'PHP 8.0+ required (current: ' . PHP_VERSION . ')';
        }

        if (!extension_loaded('gd')) {
            $issues[] = 'GD extension not loaded';
        }

        // Check output directory with Docker awareness
        $outputDir = getenv('WEATHERMAP_OUTPUT') ?: __DIR__ . '/output';
        if (!is_writable($outputDir)) {
            if ($this->docker_mode) {
                $issues[] = "Output directory not writable: $outputDir (check Docker volume permissions)";
            } else {
                $issues[] = 'Output directory not writable';
            }
        }

        // Docker-specific checks
        if ($this->docker_mode) {
            // Check if we can detect LibreNMS in container
            $librenmsPaths = ['/opt/librenms', '/app', '/var/www/html', '/usr/share/librenms'];
            $librenmsFound = false;

            foreach ($librenmsPaths as $path) {
                if (file_exists($path . '/bootstrap/app.php') || file_exists($path . '/librenms.php')) {
                    $librenmsFound = true;
                    break;
                }
            }

            if (!$librenmsFound) {
                $issues[] = 'LibreNMS not found in container (set LIBRENMS_PATH if using custom location)';
            }
        }

        return $issues;
    }

    private function setPermissions()
    {
        $paths = [
            __DIR__ . '/output' => 0775,
            __DIR__ . '/bin/map-poller.php' => 0755
        ];

        foreach ($paths as $path => $perms) {
            if (file_exists($path)) {
                chmod($path, $perms);
            }
        }
    }

    private function createDefaultConfig()
    {
        $configPath = __DIR__ . '/config/weathermapng.php';
        if (!file_exists($configPath)) {
            // Create default config file
            $defaultConfig = "<?php\nreturn " . var_export($this->getDefaultConfig(), true) . ";";
            file_put_contents($configPath, $defaultConfig);
        }
    }

    private function getDefaultConfig()
    {
        $baseConfig = [
            'docker_mode' => $this->docker_mode,
            'default_width' => 800,
            'default_height' => 600,
            'poll_interval' => 300,
            'thresholds' => [50, 80, 95],
            'scale' => 'bits',
            'rrd_base' => getenv('LIBRENMS_RRD_BASE') ?: '/opt/librenms/rrd',
            'enable_local_rrd' => true,
            'enable_api_fallback' => true,
            'cache_ttl' => 300,
            'colors' => [
                'node_up' => '#28a745',
                'node_down' => '#dc3545',
                'node_warning' => '#ffc107',
                'node_unknown' => '#6c757d',
                'link_normal' => '#28a745',
                'link_warning' => '#ffc107',
                'link_critical' => '#dc3545',
                'background' => '#ffffff',
            ],
            'rendering' => [
                'image_format' => 'png',
                'quality' => 90,
                'font_size' => 10,
                'node_radius' => 10,
                'link_width' => 2,
            ],
            'security' => [
                'allow_embed' => true,
                'max_image_size' => 2048,
            ],
            'editor' => [
                'grid_size' => 20,
                'snap_to_grid' => true,
                'auto_save' => true,
                'auto_save_interval' => 30,
            ],
        ];

        // Docker-specific overrides
        if ($this->docker_mode) {
            $baseConfig['log_to_stdout'] = getenv('LOG_TO_STDOUT') ?: true;
            $baseConfig['log_file'] = getenv('WEATHERMAP_LOG') ?: '/dev/stdout';
            $baseConfig['output_path'] = getenv('WEATHERMAP_OUTPUT') ?: __DIR__ . '/output';
        }

        return $baseConfig;
    }

    private function detectDocker()
    {
        // Check for Docker environment indicators
        $indicators = [
            getenv('DOCKER_CONTAINER'),
            getenv('LIBRENMS_DOCKER'),
            file_exists('/.dockerenv'),
        ];

        foreach ($indicators as $indicator) {
            if (!empty($indicator)) {
                return true;
            }
        }

        return false;
    }

    private function log($message)
    {
        $logFile = $this->docker_mode ?
            '/dev/stdout' :
            '/var/log/librenms/weathermapng_install.log';

        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }

    public function getVersion()
    {
        return '1.0.0';
    }

    public function getInfo()
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->getVersion(),
            'author' => 'LibreNMS Community',
            'email' => 'info@librenms.org',
            'homepage' => 'https://github.com/lance0/weathermapNG',
        ];
    }
}