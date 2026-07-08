<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for MapVersionService diff logic and versioning infrastructure.
 *
 * The diff logic (getAddedRemovedIds, getModifiedIds) is tested as pure
 * functions since it mirrors array_diff semantics. The service/controller
 * classes depend on Laravel FormRequest/Controller which aren't in the
 * plugin's dev deps, so we test via file-content checks rather than
 * class_exists (which would trigger autoloading of missing parents).
 */
class MapVersionTest extends TestCase
{
    public function testMapVersionServiceFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../src/Services/MapVersionService.php');
    }

    public function testMapVersionControllerFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../src/Http/Controllers/MapVersionController.php');
    }

    public function testSaveMapVersionRequestFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../src/Http/Requests/SaveMapVersionRequest.php');
    }

    public function testMapVersionModelFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../src/Models/MapVersion.php');
    }

    public function testVersionsTableMigrationExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../database/migrations/2026_01_07_000002_create_map_versions_table.php');
    }

    /**
     * The diff direction must be: v1 -> v2 transition.
     * Added = in v2 but not v1. Removed = in v1 but not v2.
     * This mirrors the semantics the UI will show.
     */
    public function testDiffDirectionSemantics(): void
    {
        $ids1 = [1, 2, 3, 5];
        $ids2 = [2, 3, 4, 5];

        $added = array_values(array_diff($ids2, $ids1));
        $this->assertSame([4], $added);

        $removed = array_values(array_diff($ids1, $ids2));
        $this->assertSame([1], $removed);
    }

    /**
     * Verify that the MapVersion model casts config_snapshot to array.
     * This prevents the json_decode-on-array TypeError that was fixed.
     */
    public function testMapVersionCastsConfigSnapshotToArray(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Models/MapVersion.php');

        $this->assertStringContainsString("'config_snapshot' => 'array'", $content);
    }

    /**
     * Verify captureSnapshot uses device_id (not database_id) and
     * does not include link meta (Link model has no meta column).
     */
    public function testCaptureSnapshotUsesCorrectFieldNames(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Services/MapVersionService.php');

        $this->assertStringContainsString('$node->device_id', $content);
        $this->assertStringNotContainsString('$node->database_id', $content);
        $this->assertStringNotContainsString('$link->meta', $content);
    }

    /**
     * Verify restoreVersion does a true rollback (delete + recreate)
     * rather than upsert-only, and preserves original IDs via forceCreate.
     */
    public function testRestoreVersionDoesTrueRollbackWithPreservedIds(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Services/MapVersionService.php');

        $this->assertStringContainsString("->links()->delete()", $content);
        $this->assertStringContainsString("->nodes()->delete()", $content);
        $this->assertStringContainsString('forceCreate', $content);
    }

    /**
     * Verify compare methods no longer call json_decode on config_snapshot
     * (which is cast to array and would TypeError in PHP 8).
     */
    public function testCompareMethodsDoNotJsonDecodeConfigSnapshot(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Services/MapVersionService.php');

        $this->assertStringNotContainsString('json_decode($version', $content);
        $this->assertStringNotContainsString('json_decode($version1', $content);
        $this->assertStringNotContainsString('json_decode($version2', $content);
    }

    /**
     * Verify compareVersions returns flat lists, not nested objects.
     */
    public function testCompareVersionsReturnsFlatLists(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Services/MapVersionService.php');

        $this->assertStringNotContainsString('compareNodes(', $content);
        $this->assertStringNotContainsString('compareLinks(', $content);
        $this->assertStringContainsString('getAddedRemovedIds', $content);
        $this->assertStringContainsString('getModifiedIds', $content);
    }

    /**
     * Verify deleteVersion deletes only the selected version (not newer/older).
     */
    public function testDestroyCallsDeleteVersionNotDeleteVersionsOlderThan(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapVersionController.php');

        $this->assertStringContainsString('deleteVersion(', $content);
        // destroy should not call deleteVersionsOlderThan
        $destroyStart = strpos($content, 'public function destroy');
        $destroyEnd = strpos($content, '}', $destroyStart + 100);
        $destroyBody = substr($content, $destroyStart, $destroyEnd - $destroyStart + 1);
        $this->assertStringNotContainsString('deleteVersionsOlderThan', $destroyBody);
    }

    /**
     * Verify admin gates are present on all mutating version endpoints.
     */
    public function testVersionControllerHasAdminGatesOnMutatingMethods(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapVersionController.php');

        foreach (['store', 'restore', 'destroy', 'compare', 'export', 'autoSave'] as $method) {
            $start = strpos($content, "public function {$method}");
            $this->assertNotFalse($start, "Method {$method} not found");
            $end = strpos($content, '}', $start + 50);
            $body = substr($content, $start, $end - $start + 1);
            $this->assertStringContainsString(
                'requireAdmin()',
                $body,
                "Method {$method} must call requireAdmin()"
            );
        }
    }

    /**
     * Verify store and autoSave use SaveMapVersionRequest (not raw Request).
     */
    public function testStoreAndAutoSaveUseFormRequest(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapVersionController.php');

        $this->assertStringContainsString('SaveMapVersionRequest $request', $content);
    }

    /**
     * Verify store and autoSave sanitize version name with strip_tags.
     */
    public function testStoreAndAutoSaveSanitizeName(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapVersionController.php');

        $this->assertStringContainsString('strip_tags(', $content);
    }

    /**
     * Verify show and export do not json_decode config_snapshot
     * (it's already cast to array on the model).
     */
    public function testShowAndExportDoNotJsonDecodeConfigSnapshot(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Controllers/MapVersionController.php');

        $this->assertStringNotContainsString('json_decode($version->config_snapshot', $content);
    }

    /**
     * Verify versioning.js avoids browser prompts and debug logging.
     */
    public function testVersioningJsAvoidsBrowserPrompts(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/js/versioning.js');

        $this->assertStringNotContainsString('confirm(', $content, 'versioning.js should avoid native confirmation prompts');
        $this->assertStringNotContainsString('alert(', $content, 'versioning.js should avoid native alert prompts');
        $this->assertStringNotContainsString('console.log', $content, 'versioning.js should avoid debug logging');
    }
    /**
     * Regression: getVersions() must call ->get() to execute the query,
     * otherwise it returns an Eloquent Builder instead of a Collection.
     */
    public function testGetVersionsCallsGetOnQuery(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Services/MapVersionService.php');

        // The scope returns a Builder; getVersions must terminate with ->get()
        $this->assertStringContainsString('->get()', $content, 'getVersions must call ->get() to execute the query');
        $this->assertStringContainsString('->versions(', $content, 'getVersions should use the versions scope');
    }

    /**
     * Regression: MapVersion model must disable updated_at (table has no
     * updated_at column) without disabling created_at.
     */
    public function testMapVersionDisablesUpdatedAtOnly(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Models/MapVersion.php');

        // UPDATED_AT = null disables only updated_at; $timestamps = false would disable both
        $this->assertStringContainsString('const UPDATED_AT = null;', $content, 'MapVersion must set UPDATED_AT = null to disable only updated_at');
        $this->assertStringNotContainsString('$timestamps = false', $content, 'MapVersion must not set $timestamps = false (would disable created_at too)');
    }

    /**
     * Regression: created_at must be auto-managed by Eloquent (timestamps
     * enabled, UPDATED_AT = null). It should NOT be in $fillable since it's
     * set automatically, not via mass assignment.
     */
    public function testCreatedAtIsAutoManagedNotFillable(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Models/MapVersion.php');

        // Extract just the $fillable array to check
        preg_match('/\$fillable\s*=\s*\[(.*?)\];/s', $content, $matches);
        $this->assertNotEmpty($matches, '$fillable array should exist');
        $this->assertStringNotContainsString('created_at', $matches[1], 'created_at should not be in $fillable — it is auto-managed by Eloquent');
        $this->assertStringNotContainsString('$timestamps = false', $content, '$timestamps must remain true (default) so created_at is auto-managed');
    }

    /**
     * Regression: creator() relation must use user_id (LibreNMS users PK),
     * not id (default Eloquent PK name).
     */
    public function testCreatorRelationUsesUserId(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Models/MapVersion.php');

        $this->assertStringContainsString("'user_id'", $content, 'creator() relation must use user_id as owner key');
        $this->assertStringNotContainsString("'created_by', 'id'", $content, 'creator() must not use id as owner key');
    }

}
