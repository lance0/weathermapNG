<?php

use Illuminate\Support\Facades\Route;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\LookupController;

// Additional plugin routes under the standard v2 plugin path
// Main page is provided by the v2 SinglePageHook at: /plugin/WeathermapNG

Route::group(['prefix' => 'plugin/WeathermapNG', 'middleware' => ['web', 'auth']], function () {
    // Embed view and JSON endpoints
    Route::get('/embed/{map}', [RenderController::class, 'embed'])->name('weathermapng.embed');
    Route::get('/api/maps/{map}/json', [RenderController::class, 'json'])->name('weathermapng.json');
    Route::get('/api/maps/{map}/live', [RenderController::class, 'live'])->name('weathermapng.live');
    Route::get('/api/maps/{map}/export', [RenderController::class, 'export'])->name('weathermapng.export');
    Route::post('/api/import', [RenderController::class, 'import'])->name('weathermapng.import');
    Route::get('/api/maps/{map}/sse', [RenderController::class, 'sse'])->name('weathermapng.sse');

    // Device and port lookup endpoints for editor
    Route::get('/api/devices', [LookupController::class, 'devices'])->name('weathermapng.devices');
    Route::get('/api/device/{id}/ports', [LookupController::class, 'ports'])->name('weathermapng.device.ports');

    // Optional CRUD endpoints (guard as needed)
    Route::post('/map', [MapController::class, 'create'])->name('weathermapng.map.create');
    Route::put('/map/{map}', [MapController::class, 'update'])->name('weathermapng.map.update');
    Route::delete('/map/{map}', [MapController::class, 'destroy'])->name('weathermapng.map.destroy');
    Route::post('/map/{map}/nodes', [MapController::class, 'storeNodes'])->name('weathermapng.nodes.store');
    Route::post('/map/{map}/links', [MapController::class, 'storeLinks'])->name('weathermapng.links.store');
    Route::post('/api/maps/{map}/save', [MapController::class, 'save'])->name('weathermapng.map.save');
    Route::patch('/map/{map}/node/{node}', [MapController::class, 'updateNode'])->name('weathermapng.node.update');
    Route::patch('/map/{map}/link/{link}', [MapController::class, 'updateLink'])->name('weathermapng.link.update');
});

// Health and probe endpoints (no auth for k8s/monitoring)
Route::prefix('plugin/WeathermapNG')->group(function () {
    Route::get('/health', [HealthController::class, 'check'])->name('weathermapng.health');
    Route::get('/health/stats', [HealthController::class, 'stats'])->name('weathermapng.health.stats');
    Route::get('/ready', [HealthController::class, 'ready'])->name('weathermapng.ready');
    Route::get('/live', [HealthController::class, 'live'])->name('weathermapng.alive');
    Route::get('/metrics', [HealthController::class, 'metrics'])->name('weathermapng.metrics');
});
