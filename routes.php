<?php

use Illuminate\Support\Facades\Route;
use App\Plugins\WeathermapNG\Http\Controllers\WeathermapNGController;

// WeathermapNG Plugin Routes - LibreNMS v2 format
Route::group(['prefix' => 'plugin/weathermapng', 'middleware' => ['web', 'auth']], function () {
    // Main plugin page (handled by Page hook)
    Route::get('/', [WeathermapNGController::class, 'index'])->name('weathermapng.index');

    // Map viewing and editing
    Route::get('/map/{id}', [WeathermapNGController::class, 'show'])->name('weathermapng.show');
    Route::get('/map/{id}/edit', [WeathermapNGController::class, 'edit'])->name('weathermapng.edit');

    // Map CRUD operations
    Route::post('/map', [WeathermapNGController::class, 'store'])->name('weathermapng.store');
    Route::put('/map/{id}', [WeathermapNGController::class, 'update'])->name('weathermapng.update');
    Route::delete('/map/{id}', [WeathermapNGController::class, 'destroy'])->name('weathermapng.destroy');

    // API endpoints for map data
    Route::get('/api/map/{id}/data', [WeathermapNGController::class, 'data'])->name('weathermapng.data');
    Route::get('/api/map/{id}/live', [WeathermapNGController::class, 'live'])->name('weathermapng.live');
    Route::get('/api/map/{id}/export', [WeathermapNGController::class, 'export'])->name('weathermapng.export');
    Route::post('/api/import', [WeathermapNGController::class, 'import'])->name('weathermapng.import');

    // Device and port lookup for editor
    Route::get('/api/devices', [WeathermapNGController::class, 'devices'])->name('weathermapng.devices');
    Route::get('/api/devices/search', [WeathermapNGController::class, 'searchDevices'])->name('weathermapng.devices.search');
    Route::get('/api/device/{id}/ports', [WeathermapNGController::class, 'ports'])->name('weathermapng.ports');

    // Node and link management
    Route::post('/map/{id}/nodes', [WeathermapNGController::class, 'storeNodes'])->name('weathermapng.nodes.store');
    Route::post('/map/{id}/links', [WeathermapNGController::class, 'storeLinks'])->name('weathermapng.links.store');
});

// Health check routes (no auth required for monitoring)
Route::prefix('plugin/weathermapng')->group(function () {
    Route::get('health', [WeathermapNGController::class, 'health'])->name('weathermapng.health');
    Route::get('ready', [WeathermapNGController::class, 'ready'])->name('weathermapng.ready');
});