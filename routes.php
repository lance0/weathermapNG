<?php

use Illuminate\Support\Facades\Route;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\LookupController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\PageController;

// Plugin routes under the standard v2 plugin path

Route::group(['prefix' => 'plugin/WeathermapNG', 'middleware' => ['web', 'auth']], function () {
    // Main pages
    Route::get('/', [PageController::class, 'index'])->name('weathermapng.index');
    Route::get('/editor/{map?}', [PageController::class, 'editor'])->name('weathermapng.editor');
    Route::get('/view/{map}', [PageController::class, 'view'])->name('weathermapng.view');
    Route::get('/settings', [PageController::class, 'settings'])->name('weathermapng.settings');
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
    Route::get('/editor-d3/{map}', [MapController::class, 'editorD3'])->name('weathermapng.editor.d3');
    Route::put('/map/{map}', [MapController::class, 'update'])->name('weathermapng.map.update');
    Route::delete('/map/{map}', [MapController::class, 'destroy'])->name('weathermapng.map.destroy');
    Route::post('/map/{map}/nodes', [MapController::class, 'storeNodes'])->name('weathermapng.nodes.store');
    Route::post('/map/{map}/links', [MapController::class, 'storeLinks'])->name('weathermapng.links.store');
    Route::post('/api/maps/{map}/save', [MapController::class, 'save'])->name('weathermapng.map.save');
    Route::patch('/map/{map}/node/{node}', [MapController::class, 'updateNode'])->name('weathermapng.node.update');
    Route::patch('/map/{map}/link/{link}', [MapController::class, 'updateLink'])->name('weathermapng.link.update');
    Route::post('/map/{map}/node', [MapController::class, 'createNode'])->name('weathermapng.node.create');
    Route::delete('/map/{map}/node/{node}', [MapController::class, 'deleteNode'])->name('weathermapng.node.delete');
    Route::post('/map/{map}/link', [MapController::class, 'createLink'])->name('weathermapng.link.create');
    Route::delete('/map/{map}/link/{link}', [MapController::class, 'deleteLink'])->name('weathermapng.link.delete');
});

// Health and probe endpoints (no auth for k8s/monitoring)
Route::prefix('plugin/WeathermapNG')->group(function () {
    Route::get('/health', [HealthController::class, 'check'])->name('weathermapng.health');
    Route::get('/health/stats', [HealthController::class, 'stats'])->name('weathermapng.health.stats');
    Route::get('/ready', [HealthController::class, 'ready'])->name('weathermapng.ready');
    Route::get('/live', [HealthController::class, 'live'])->name('weathermapng.alive');
    Route::get('/metrics', [HealthController::class, 'metrics'])->name('weathermapng.metrics');
});
