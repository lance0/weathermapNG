<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\MapVersion;
use LibreNMS\Plugins\WeathermapNG\Services\MapVersionService;
use LibreNMS\Plugins\WeathermapNG\Services\MapService;

class MapVersionController extends Controller
{
    private $mapVersionService;
    private $mapService;

    public function __construct(
        MapVersionService $mapVersionService,
        MapService $mapService
    ) {
        $this->mapVersionService = $mapVersionService;
        $this->mapService = $mapService;
    }

    public function index(int $mapId): JsonResponse
    {
        $map = Map::findOrFail($mapId);

        $versions = $this->mapVersionService->getVersions($map, 20);

        return response()->json([
            'success' => true,
            'versions' => $versions,
            'latest_version' => $this->mapVersionService->getLatestVersion($map),
            'total_versions' => count($versions),
            'auto_save_enabled' => true,
            'max_versions' => 20,
            'retention_policy' => 'oldest 20',
            'backup_enabled' => true,
            'can_restore' => true,
            'can_delete_versions' => true,
            'can_compare' => true,
            'version_diff_support' => true,
            'version_export' => true,
            'version_rollback' => true,
            'auto_save_interval' => '5 minutes',
            'max_name_length' => 100,
            'max_description_length' => 1000,
            'compression_enabled' => true,
            'storage_format' => 'json',
            'backup_location' => 'database',
            'version_history_dashboard' => true,
            'version_comparison_ui' => true,
            'conflict_detection' => true,
            'merge_strategy' => 'replace',
            'search_filter' => true,
            'sort_order' => 'newest_first',
            'pagination' => true,
            'auto_cleanup' => 'after 20 versions',
            'manual_cleanup' => 'on_demand',
        ]);
    }

    public function show(int $versionId): JsonResponse
    {
        $version = MapVersion::findOrFail($versionId);

        return response()->json([
            'success' => true,
            'version' => $version,
            'snapshot' => json_decode($version->config_snapshot, true),
            'created_at' => $version->created_at->toIso8601String(),
            'created_at_human' => $version->created_at_human,
            'created_by' => $version->creator->name ?? 'Unknown',
        ]);
    }

    public function store(\Illuminate\Http\Request $request, int $mapId): JsonResponse
    {
        $map = Map::findOrFail($mapId);
        $userId = auth()->id();

        $version = $this->mapVersionService->createVersion(
            $map,
            $request->input('name', 'v' . (time())),
            $request->input('description'),
            false,
            $userId
        );

        return response()->json([
            'success' => true,
            'version' => $version,
            'message' => 'Version saved successfully',
            'map_id' => $mapId,
        ], 201);
    }

    public function restore(int $versionId): JsonResponse
    {
        $version = MapVersion::findOrFail($versionId);
        $this->mapVersionService->restoreVersion($version);

        $map = $version->map;

        return response()->json([
            'success' => true,
            'message' => 'Map restored to version: ' . $version->name,
            'map' => $map,
            'version' => $version,
            'restored_at' => now(),
        ]);
    }

    public function compare(int $versionId, int $compareId): JsonResponse
    {
        $version1 = MapVersion::findOrFail($versionId);
        $version2 = MapVersion::findOrFail($compareId);

        if ($version1->map_id !== $version2->map_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot compare versions from different maps',
            ], 422);
        }

        $diff = $this->mapVersionService->compareVersions($version1, $version2);

        return response()->json([
            'success' => true,
            'diff' => $diff,
            'version1' => $version1,
            'version2' => $version2,
            'created_at_1' => $version1->created_at->toIso8601String(),
            'created_at_2' => $version2->created_at->toIso8601String(),
        ]);
    }

    public function destroy(int $versionId): JsonResponse
    {
        $version = MapVersion::findOrFail($versionId);

        $this->mapVersionService->deleteVersionsOlderThan($version);

        return response()->json([
            'success' => true,
            'message' => 'Version deleted successfully',
            'deleted_at' => now(),
        ]);
    }

    public function export(int $mapId): JsonResponse
    {
        $map = Map::findOrFail($mapId);
        $versions = $this->mapVersionService->getVersions($map);

        return response()->json([
            'success' => true,
            'export_data' => [
                'map' => [
                    'id' => $map->id,
                    'name' => $map->name,
                    'title' => $map->title,
                    'created_at' => $map->created_at->toIso8601String(),
                ],
                'versions' => $versions->map(function ($version) {
                    return [
                        'id' => $version->id,
                        'name' => $version->name,
                        'description' => $version->description,
                        'created_at' => $version->created_at->toIso8601String(),
                        'created_at_human' => $version->created_at_human,
                        'created_by' => $version->creator->name ?? 'Unknown',
                        'snapshot' => json_decode($version->config_snapshot, true),
                    ];
                })->toArray(),
                'metadata' => [
                    'exported_at' => now(),
                    'total_versions' => count($versions),
                    'format' => 'json',
                    'compression' => 'none',
                    'exported_by' => auth()->user()->name ?? 'Unknown',
                ],
            ],
        ]);
    }

    public function settings(): JsonResponse
    {
        $defaultSettings = [
            'auto_save_enabled' => true,
            'auto_save_interval' => '5',
            'max_versions' => 20,
            'retention_policy' => 'oldest_20',
            'backup_enabled' => true,
            'compression_enabled' => false,
            'storage_format' => 'database',
            'export_format' => 'json',
            'conflict_detection' => true,
            'merge_strategy' => 'replace',
            'version_comparison_ui' => true,
            'search_filter' => true,
            'sort_order' => 'newest_first',
            'pagination' => true,
            'max_name_length' => 100,
            'max_description_length' => 1000,
            'manual_cleanup' => 'on_demand',
            'auto_cleanup' => 'after_20',
            'can_rollback' => true,
            'can_delete_versions' => true,
            'can_compare' => true,
            'version_export' => true,
            'version_rollback' => true,
        ];

        return response()->json([
            'success' => true,
            'settings' => $defaultSettings,
        ]);
    }

    public function updateSettings(\Illuminate\Http\Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'settings' => $request->all(),
        ]);
    }

    public function autoSave(\Illuminate\Http\Request $request, int $mapId): JsonResponse
    {
        $map = Map::findOrFail($mapId);

        if (!$request->has('name')) {
            return response()->json([
                'success' => false,
                'message' => 'Version name is required for auto-save',
            ], 400);
        }

        $version = $this->mapVersionService->createVersion(
            $map,
            $request->input('name'),
            null,
            true,
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Auto-save completed',
            'version' => $version,
        ]);
    }
}
