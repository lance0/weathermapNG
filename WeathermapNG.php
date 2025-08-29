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
        // Plugin activation logic
        // Could run migrations, set up permissions, etc.
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