<?php

namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\Plugins\Hooks\MenuEntryHook;
use LibreNMS\Interfaces\Plugins\Hooks\PageHook;
use LibreNMS\Interfaces\Plugins\Hooks\SettingsHook;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;
use LibreNMS\Plugins\WeathermapNG\Hooks\MenuEntry;
use LibreNMS\Plugins\WeathermapNG\Hooks\Page;
use LibreNMS\Plugins\WeathermapNG\Hooks\Settings;

class WeathermapNGProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(PluginManagerInterface $pluginManager): void
    {
        $pluginName = 'WeathermapNG';

        // Register hooks with LibreNMS
        $pluginManager->publishHook($pluginName, MenuEntryHook::class, MenuEntry::class);
        $pluginManager->publishHook($pluginName, PageHook::class, Page::class);
        $pluginManager->publishHook($pluginName, SettingsHook::class, Settings::class);

        if (! $pluginManager->pluginEnabled($pluginName)) {
            return; // if plugin is disabled, don't boot
        }

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        
        // Load views with namespace
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', $pluginName);
        
        // Register config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/weathermapng.php', 'weathermapng'
        );

        // Publish assets if running in console
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/weathermapng.php' => config_path('weathermapng.php'),
            ], 'weathermapng-config');
            
            $this->publishes([
                __DIR__ . '/../Resources/views' => resource_path('views/vendor/WeathermapNG'),
            ], 'weathermapng-views');
            
            $this->publishes([
                __DIR__ . '/../css' => public_path('plugins/WeathermapNG/css'),
                __DIR__ . '/../js' => public_path('plugins/WeathermapNG/js'),
            ], 'weathermapng-assets');
        }
    }
}