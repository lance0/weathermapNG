<?php

namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Support\ServiceProvider;

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
        // Register views with namespace
        $this->loadViewsFrom(__DIR__ . '/../Resources/views/weathermapng', 'weathermapng');
        
        // Register routes
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        
        // Register migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        // Publish assets if needed
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/weathermapng.php' => config_path('weathermapng.php'),
            ], 'weathermapng-config');
            
            // Publish views
            $this->publishes([
                __DIR__ . '/../Resources/views' => resource_path('views/vendor/weathermapng'),
            ], 'weathermapng-views');
            
            // Publish assets
            $this->publishes([
                __DIR__ . '/../css' => public_path('plugins/WeathermapNG/css'),
                __DIR__ . '/../js' => public_path('plugins/WeathermapNG/js'),
            ], 'weathermapng-assets');
        }
    }
}