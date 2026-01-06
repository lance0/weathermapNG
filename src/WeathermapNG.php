<?php

namespace LibreNMS\Plugins\WeathermapNG;

class WeathermapNG
{
    public $name = 'WeathermapNG';
    public $description = 'Modern interactive network weathermap for LibreNMS';
    public $architecture = 'hook-based';
    public $librenms_version = '24.x+';

    public function __construct()
    {
        // Plugin initialization is handled by LibreNMS hooks
        // See: app/Plugins/WeathermapNG/ directory
    }

    public function activate()
    {
        // Activation logic is handled by hooks
        // This method exists for compatibility only
        return true;
    }

    public function deactivate()
    {
        // Deactivation logic is handled by hooks
        // This method exists for compatibility only
        return true;
    }

    public function uninstall()
    {
        // Cleanup logic is handled by hooks
        // This method exists for compatibility only
        return true;
    }

    public function getVersion()
    {
        return '1.2.2';
    }

    public function getInfo()
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->getVersion(),
            'author' => 'LibreNMS Community',
            'email' => 'info@librenms.org',
            'architecture' => $this->architecture,
            'librenms_version' => $this->librenms_version,
            'hooks' => [
                'menu' => 'app/Plugins/WeathermapNG/Menu.php',
                'page' => 'app/Plugins/WeathermapNG/Page.php',
                'settings' => 'app/Plugins/WeathermapNG/Settings.php'
            ],
            'note' => 'This plugin uses LibreNMS 24.x hook-based architecture. ' .
                      'Functionality is implemented via hooks, not this bootstrap file.'
        ];
    }

    /**
     * Get hook information for debugging
     */
    public function getHooksInfo()
    {
        return [
            'menu_hook' => 'Adds WeathermapNG to LibreNMS navigation menu',
            'page_hook' => 'Provides main weathermap interface and editor',
            'settings_hook' => 'Adds weathermap configuration to LibreNMS settings',
            'location' => 'app/Plugins/WeathermapNG/',
            'architecture' => 'Hook-based (LibreNMS 24.x+)'
        ];
    }

    public function checkRequirements()
    {
        $requirements = [
            'php' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'gd' => extension_loaded('gd'),
            'json' => extension_loaded('json'),
            'pdo' => extension_loaded('pdo'),
            'mbstring' => extension_loaded('mbstring'),
        ];

        return $requirements;
    }

    public function getDefaultConfig()
    {
        return [
            'default_width' => 800,
            'default_height' => 600,
            'poll_interval' => 300,
            'thresholds' => [50, 80, 95],
            'scale' => 'bits',
            'rrd_base' => '/opt/librenms/rrd',
            'rrdcached' => ['socket' => null],
            'enable_local_rrd' => true,
            'enable_api_fallback' => true,
            'cache_ttl' => 300,
            'enable_sse' => true,
            'client_refresh' => 60,
            'snmp' => [
                'enabled' => false,
                'version' => '2c',
                'community' => null,
                'timeout' => 1,
                'retries' => 1,
            ],
            'api_token' => null,
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
                'embed_domains' => ['localhost', '*.yourdomain.com'],
                'max_image_size' => 2048,
            ],
            'editor' => [
                'grid_size' => 20,
                'snap_to_grid' => true,
                'auto_save' => true,
                'auto_save_interval' => 30,
            ],
        ];
    }
}