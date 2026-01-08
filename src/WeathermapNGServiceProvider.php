<?php

namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Support\ServiceProvider;
use LibreNMS\Plugins\WeathermapNG\Services\MapVersionService;
use LibreNMS\Plugins\WeathermapNG\Services\MapCacheService;
use LibreNMS\Plugins\WeathermapNG\Services\DevicePortLookup;
use LibreNMS\Plugins\WeathermapNG\Services\RrdDataService;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;
use LibreNMS\Plugins\WeathermapNG\RRD\RRDTool;

class WeathermapNGServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Core services
        $this->app->singleton(MapVersionService::class);
        $this->app->singleton(MapCacheService::class);
        $this->app->singleton(DevicePortLookup::class);

        // RRD data layer
        $this->app->singleton(RRDTool::class);
        $this->app->singleton(RrdDataService::class, function ($app) {
            return new RrdDataService($app->make(RRDTool::class));
        });

        // Port utilization (RRD-only)
        $this->app->singleton(PortUtilService::class, function ($app) {
            return new PortUtilService($app->make(RrdDataService::class));
        });
    }

    public function boot(): void
    {
        // Gate policies are optional - only register if Gate facade is available
    }
}
