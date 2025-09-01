<?php

namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;

class WeathermapNGServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/weathermapng.php', 'weathermapng'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Views for v2 hook rendering use plugin namespace 'WeathermapNG'
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'WeathermapNG');

        // Routes (API/embed)
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');

        // Register v2 hooks via PluginManager (packaged plugin mode)
        try {
            /** @var PluginManagerInterface $pm */
            $pm = $this->app->make(PluginManagerInterface::class);
            $pluginName = 'WeathermapNG';
            $pm->publishHook($pluginName, \App\Plugins\Hooks\MenuEntryHook::class, \LibreNMS\Plugins\WeathermapNG\Hooks\Menu::class);
            $pm->publishHook($pluginName, \App\Plugins\Hooks\PageHook::class, \LibreNMS\Plugins\WeathermapNG\Hooks\Page::class);
            $pm->publishHook($pluginName, \App\Plugins\Hooks\SettingsHook::class, \LibreNMS\Plugins\WeathermapNG\Hooks\Settings::class);
            // Optional hooks if present
            if (class_exists(\LibreNMS\Plugins\WeathermapNG\Hooks\DeviceOverview::class)) {
                $pm->publishHook($pluginName, \App\Plugins\Hooks\DeviceOverviewHook::class, \LibreNMS\Plugins\WeathermapNG\Hooks\DeviceOverview::class);
            }
            if (class_exists(\LibreNMS\Plugins\WeathermapNG\Hooks\PortTab::class)) {
                $pm->publishHook($pluginName, \App\Plugins\Hooks\PortTabHook::class, \LibreNMS\Plugins\WeathermapNG\Hooks\PortTab::class);
            }
        } catch (\Throwable $e) {
            // Ignore if PluginManager not available (older versions)
        }

        // Optional publish in console
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/weathermapng.php' => config_path('weathermapng.php'),
            ], 'weathermapng-config');
        }
    }
}
