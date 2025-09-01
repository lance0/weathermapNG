<?php

use Illuminate\Support\Facades\Route;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\LookupController;
use LibreNMS\Plugins\WeathermapNG\Http\Controllers\PageController;

// Plugin routes under the standard v2 plugin path

Route::middleware(['web', 'auth'])->group(function () {
    // Main pages under plugin path
    Route::get('plugin/WeathermapNG', [PageController::class, 'index'])->name('weathermapng.index');
    Route::get('plugin/WeathermapNG/editor/{map?}', [PageController::class, 'editor'])->name('weathermapng.editor');
    Route::get('plugin/WeathermapNG/view/{map}', [PageController::class, 'view'])->name('weathermapng.view');
    // Embed view and JSON endpoints
    Route::get('plugin/WeathermapNG/embed/{map}', [RenderController::class, 'embed'])->name('weathermapng.embed');
    Route::get('plugin/WeathermapNG/api/maps/{map}/json', [RenderController::class, 'json'])->name('weathermapng.json');
    Route::get('plugin/WeathermapNG/api/maps/{map}/live', [RenderController::class, 'live'])->name('weathermapng.live');
    Route::get('plugin/WeathermapNG/api/maps/{map}/export', [RenderController::class, 'export'])->name('weathermapng.export');
    Route::post('plugin/WeathermapNG/api/import', [RenderController::class, 'import'])->name('weathermapng.import');
    Route::get('plugin/WeathermapNG/api/maps/{map}/sse', [RenderController::class, 'sse'])->name('weathermapng.sse');

    // Device and port lookup endpoints for editor
    Route::get('plugin/WeathermapNG/api/devices', [LookupController::class, 'devices'])->name('weathermapng.devices');
    Route::get('plugin/WeathermapNG/api/device/{id}/ports', [LookupController::class, 'ports'])->name('weathermapng.device.ports');

    // Optional CRUD endpoints (guard as needed)
    Route::post('plugin/WeathermapNG/map', [MapController::class, 'create'])->name('weathermapng.map.create');
    Route::get('plugin/WeathermapNG/editor-d3/{map}', [MapController::class, 'editorD3'])->name('weathermapng.editor.d3');
    Route::put('plugin/WeathermapNG/map/{map}', [MapController::class, 'update'])->name('weathermapng.map.update');
    Route::delete('plugin/WeathermapNG/map/{map}', [MapController::class, 'destroy'])->name('weathermapng.map.destroy');
    Route::post('plugin/WeathermapNG/map/{map}/nodes', [MapController::class, 'storeNodes'])->name('weathermapng.nodes.store');
    Route::post('plugin/WeathermapNG/map/{map}/links', [MapController::class, 'storeLinks'])->name('weathermapng.links.store');
    Route::post('plugin/WeathermapNG/api/maps/{map}/save', [MapController::class, 'save'])->name('weathermapng.map.save');
    Route::patch('plugin/WeathermapNG/map/{map}/node/{node}', [MapController::class, 'updateNode'])->name('weathermapng.node.update');
    Route::patch('plugin/WeathermapNG/map/{map}/link/{link}', [MapController::class, 'updateLink'])->name('weathermapng.link.update');
    Route::post('plugin/WeathermapNG/map/{map}/node', [MapController::class, 'createNode'])->name('weathermapng.node.create');
    Route::delete('plugin/WeathermapNG/map/{map}/node/{node}', [MapController::class, 'deleteNode'])->name('weathermapng.node.delete');
    Route::post('plugin/WeathermapNG/map/{map}/link', [MapController::class, 'createLink'])->name('weathermapng.link.create');
    Route::delete('plugin/WeathermapNG/map/{map}/link/{link}', [MapController::class, 'deleteLink'])->name('weathermapng.link.delete');
});

// Health and probe endpoints (no auth for k8s/monitoring)
Route::prefix('plugin/WeathermapNG')->group(function () {
    Route::get('/health', [HealthController::class, 'check'])->name('weathermapng.health');
    Route::get('/health/stats', [HealthController::class, 'stats'])->name('weathermapng.health.stats');
    Route::get('/ready', [HealthController::class, 'ready'])->name('weathermapng.ready');
    Route::get('/live', [HealthController::class, 'live'])->name('weathermapng.alive');
    Route::get('/metrics', [HealthController::class, 'metrics'])->name('weathermapng.metrics');
});
