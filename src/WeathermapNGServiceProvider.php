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

        // Register hooks
        $pluginManager->publishHook($pluginName, \LibreNMS\Interfaces\Plugins\MenuEntryHook::class, \App\Plugins\WeathermapNG\Menu::class);
        $pluginManager->publishHook($pluginName, \LibreNMS\Interfaces\Plugins\PageHook::class, \App\Plugins\WeathermapNG\Page::class);
        $pluginManager->publishHook($pluginName, \LibreNMS\Interfaces\Plugins\SettingsHook::class, \App\Plugins\WeathermapNG\Settings::class);

        // Check if plugin is enabled
        if (!$pluginManager->pluginEnabled($pluginName)) {
            return;
        }

        // Register views with namespace
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'weathermapng');
        
        // Register routes
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');

        // Register translations if any
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'weathermapng');

        // Publish assets
        $this->publishes([
            __DIR__ . '/../public/css' => public_path('plugins/weathermapng/css'),
            __DIR__ . '/../public/js' => public_path('plugins/weathermapng/js'),
            __DIR__ . '/../public/images' => public_path('plugins/weathermapng/images'),
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