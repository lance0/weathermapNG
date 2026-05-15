<?php

namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\Plugins\Hooks\MenuEntryHook;
use LibreNMS\Interfaces\Plugins\Hooks\SettingsHook;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;
use LibreNMS\Plugins\WeathermapNG\Hooks\MenuEntry;
use LibreNMS\Plugins\WeathermapNG\Hooks\Settings;

class WeathermapNGProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(PluginManagerInterface $pluginManager): void
    {
        $pluginName = 'WeathermapNG';

        // Register hooks with LibreNMS (only MenuEntry and Settings are supported)
        $pluginManager->publishHook($pluginName, MenuEntryHook::class, MenuEntry::class);
        $pluginManager->publishHook($pluginName, SettingsHook::class, Settings::class);

        // Register config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/config.php',
            'weathermapng'
        );

        // Keep install and health routes available before the plugin is enabled.
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load views with namespace
        $this->loadViewsFrom(__DIR__ . '/../resources/views', $pluginName);

        if (! $pluginManager->pluginEnabled($pluginName)) {
            return; // if plugin is disabled, hooks and publishable assets are skipped
        }

        // Publish assets if running in console
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('weathermapng.php'),
            ]);
        }
    }
}
