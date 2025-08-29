<?php

use Illuminate\Support\Facades\Route;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController;

Route::middleware(['auth'])->prefix('plugins/weathermapng')->group(function () {

    // Main map management routes
    Route::get('/', [MapController::class, 'index'])->name('weathermapng.index');
    Route::post('/maps', [MapController::class, 'create'])->name('weathermapng.create');
    Route::get('/maps/{map}', [MapController::class, 'show'])->name('weathermapng.show');
    Route::put('/maps/{map}', [MapController::class, 'update'])->name('weathermapng.update');
    Route::delete('/maps/{map}', [MapController::class, 'destroy'])->name('weathermapng.destroy');

    // Editor routes
    Route::get('/maps/{map}/editor', [MapController::class, 'editor'])->name('weathermapng.editor');
    Route::post('/maps/{map}/nodes', [MapController::class, 'storeNodes'])->name('weathermapng.nodes.store');
    Route::post('/maps/{map}/links', [MapController::class, 'storeLinks'])->name('weathermapng.links.store');

    // JSON API routes
    Route::get('/api/maps/{map}', [RenderController::class, 'json'])->name('weathermapng.api.json');
    Route::get('/api/maps/{map}/live', [RenderController::class, 'live'])->name('weathermapng.api.live');

    // Embed routes
    Route::get('/embed/{map}', [RenderController::class, 'embed'])->name('weathermapng.embed');

    // Import/Export routes
    Route::get('/api/maps/{map}/export', [RenderController::class, 'export'])->name('weathermapng.export');
    Route::post('/api/maps/import', [RenderController::class, 'import'])->name('weathermapng.import');

    // Device/Port lookup routes (for editor)
    Route::get('/api/devices', function() {
        $service = new \LibreNMS\Plugins\WeathermapNG\Services\DevicePortLookup();
        return response()->json(['devices' => $service->getAllDevices()]);
    })->name('weathermapng.api.devices');

    Route::get('/api/devices/search', function(\Illuminate\Http\Request $request) {
        $query = $request->get('q', '');
        $service = new \LibreNMS\Plugins\WeathermapNG\Services\DevicePortLookup();
        return response()->json(['devices' => $service->deviceAutocomplete($query)]);
    })->name('weathermapng.api.devices.search');

    Route::get('/api/devices/{deviceId}/ports', function($deviceId) {
        $service = new \LibreNMS\Plugins\WeathermapNG\Services\DevicePortLookup();
        return response()->json(['ports' => $service->portsForDevice((int) $deviceId)]);
    })->name('weathermapng.api.device.ports');

    // Health check routes (no auth required for monitoring)
    Route::get('/health', [HealthController::class, 'check'])->name('weathermapng.health');
    Route::get('/health/stats', [HealthController::class, 'stats'])->name('weathermapng.health.stats');
});

// Public routes (no auth required)
Route::prefix('plugins/weathermapng')->group(function () {
    // Public embed routes (if configured)
    Route::get('/public/embed/{map}', [RenderController::class, 'embed'])
        ->name('weathermapng.public.embed');
});
