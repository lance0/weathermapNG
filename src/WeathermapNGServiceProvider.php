<?php

namespace LibreNMS\Plugins\WeathermapNG;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use LibreNMS\Plugins\WeathermapNG\Services\MapVersionService;
use LibreNMS\Plugins\WeathermapNG\Policies\MapPolicy;
use LibreNMS\Plugins\WeathermapNG\Policies\NodePolicy;
use LibreNMS\Plugins\WeathermapNG\Models\Map;

class WeathermapNGServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MapVersionService::class, function ($app) {
            return new MapVersionService();
        });
    }

    public function boot(): void
    {
        // Gate policies are optional - only register if Gate facade is available
    }
}
