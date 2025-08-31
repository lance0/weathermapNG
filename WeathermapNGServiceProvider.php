<?php
namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Plugins\PluginManager;

class WeathermapNGServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register configuration
        $this->mergeConfigFrom(
            __DIR__ . '/config/weathermapng.php',
            'weathermapng'
        );

        // Register policies
        $this->app->bind(
            \LibreNMS\Plugins\WeathermapNG\Policies\MapPolicy::class
        );
        
        // Register hooks
        $this->registerHooks();
    }
    
    /**
     * Register plugin hooks
     */
    protected function registerHooks(): void
    {
        if (class_exists('\App\Plugins\PluginManager')) {
            $pluginManager = app(PluginManager::class);
            
            // Register each hook
            $pluginManager->registerHook('WeathermapNG', 'Menu', \LibreNMS\Plugins\WeathermapNG\Hooks\Menu::class);
            $pluginManager->registerHook('WeathermapNG', 'DeviceOverview', \LibreNMS\Plugins\WeathermapNG\Hooks\DeviceOverview::class);
            $pluginManager->registerHook('WeathermapNG', 'PortTab', \LibreNMS\Plugins\WeathermapNG\Hooks\PortTab::class);
            $pluginManager->registerHook('WeathermapNG', 'Settings', \LibreNMS\Plugins\WeathermapNG\Hooks\Settings::class);
            $pluginManager->registerHook('WeathermapNG', 'Page', \LibreNMS\Plugins\WeathermapNG\Hooks\Page::class);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register view namespace
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'WeathermapNG');

        // Register view namespace with plugins prefix (alternative)
        View::addNamespace('plugins.WeathermapNG', __DIR__ . '/Resources/views');

        // Load routes
        if (file_exists(__DIR__ . '/routes.php')) {
            require __DIR__ . '/routes.php';
        }

        // Register policies
        \Illuminate\Support\Facades\Gate::policy(
            \LibreNMS\Plugins\WeathermapNG\Models\Map::class,
            \LibreNMS\Plugins\WeathermapNG\Policies\MapPolicy::class
        );

        // Publish configuration (optional)
        $this->publishes([
            __DIR__ . '/config/weathermapng.php' => config_path('weathermapng.php'),
        ], 'weathermapng-config');

        // Publish assets (optional)
        $this->publishes([
            __DIR__ . '/Resources/js' => public_path('vendor/weathermapng/js'),
            __DIR__ . '/Resources/css' => public_path('vendor/weathermapng/css'),
        ], 'weathermapng-assets');
    }
}