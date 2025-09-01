<?php

namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;

class WeathermapNGServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Register plugin configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/weathermapng.php', 'weathermapng');
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $pluginName = 'WeathermapNG';
        $pluginManager = $this->app->make(PluginManagerInterface::class);

        // Register v2 plugin hooks (use core Hook base classes)
        // Note: these are only effective if this ServiceProvider is loaded.
        $pluginManager->publishHook($pluginName, \App\Plugins\Hooks\MenuEntryHook::class, \LibreNMS\Plugins\WeathermapNG\Hooks\Menu::class);
        $pluginManager->publishHook($pluginName, \App\Plugins\Hooks\PageHook::class, \LibreNMS\Plugins\WeathermapNG\Hooks\Page::class);
        $pluginManager->publishHook($pluginName, \App\Plugins\Hooks\SettingsHook::class, \LibreNMS\Plugins\WeathermapNG\Hooks\Settings::class);
        // Optional additional hooks if available
        if (class_exists(\LibreNMS\Plugins\WeathermapNG\Hooks\DeviceOverview::class)) {
            $pluginManager->publishHook($pluginName, \App\Plugins\Hooks\DeviceOverviewHook::class, \LibreNMS\Plugins\WeathermapNG\Hooks\DeviceOverview::class);
        }
        if (class_exists(\LibreNMS\Plugins\WeathermapNG\Hooks\PortTab::class)) {
            $pluginManager->publishHook($pluginName, \App\Plugins\Hooks\PortTabHook::class, \LibreNMS\Plugins\WeathermapNG\Hooks\PortTab::class);
        }

        // Check if plugin is enabled
        if (!$pluginManager->pluginEnabled($pluginName)) {
            return;
        }

        // Register views with correct namespace and path
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'WeathermapNG');
        
        // Register routes
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');

        // Register translations if any
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'weathermapng');

        // Publish assets (optional)
        $this->publishes([
            __DIR__ . '/../css' => public_path('plugins/WeathermapNG/css'),
            __DIR__ . '/../js' => public_path('plugins/WeathermapNG/js'),
        ], 'weathermapng-assets');

        // Publish config
        $this->publishes([
            __DIR__ . '/../config/weathermapng.php' => config_path('weathermapng.php'),
        ], 'weathermapng-config');

        // Register console commands if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Add any console commands here
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['weathermapng'];
    }
}
