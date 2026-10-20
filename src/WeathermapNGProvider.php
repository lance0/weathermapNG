<?php

namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\Plugins\Hooks\MenuEntryHook;
use LibreNMS\Interfaces\Plugins\Hooks\SettingsHook;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;
use LibreNMS\Plugins\WeathermapNG\Console\Commands\CreateMapCommand;
use LibreNMS\Plugins\WeathermapNG\Console\Commands\DiscoverCommand;
use LibreNMS\Plugins\WeathermapNG\Console\Commands\ExportMapCommand;
use LibreNMS\Plugins\WeathermapNG\Console\Commands\ListMapsCommand;
use LibreNMS\Plugins\WeathermapNG\Hooks\MenuEntry;
use LibreNMS\Plugins\WeathermapNG\Hooks\Settings;

class WeathermapNGProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->commands([
            CreateMapCommand::class,
            ListMapsCommand::class,
            ExportMapCommand::class,
            DiscoverCommand::class,
        ]);
    }

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

        // Overlay the settings saved via the plugin settings form onto the
        // config defaults so admin-configured values actually take effect.
        // The form persists to the plugins.settings JSON column; without
        // this merge, runtime config only reflects the file defaults.
        try {
            $pluginRow = \App\Models\Plugin::where('plugin_name', $pluginName)->first();
            $stored = $pluginRow?->settings;
            if (is_array($stored) && $stored !== []) {
                // Only overlay scalar values and known array keys; a stale
                // or malformed key must not corrupt the defaults array.
                $config = config('weathermapng');
                if (is_array($config)) {
                    foreach ($stored as $key => $value) {
                        if (array_key_exists($key, $config)) {
                            if (is_array($config[$key]) && is_array($value)) {
                                config(['weathermapng.' . $key => array_merge($config[$key], $value)]);
                            } elseif (!is_array($config[$key])) {
                                config(['weathermapng.' . $key => $value]);
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // DB unavailable during install/migrations — file defaults apply.
        }

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
