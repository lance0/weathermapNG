<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for post-audit round 2 security/correctness fixes.
 *
 * These tests verify that the codebase contains the hardened patterns
 * (admin gates, field whitelisting, PID removal, SSE clamping, null guards,
 * recursive option merging, scoped version lookups) and does not contain
 * the vulnerable patterns they replaced.
 */
class PostAudit2SecurityTest extends TestCase
{
    private string $base = __DIR__ . '/..';

    // ── MapVersionController: admin gates + nullsafe creator ────────────

    public function test_version_index_has_require_admin(): void
    {
        $content = file_get_contents($this->base . '/src/Http/Controllers/MapVersionController.php');
        // index method should call requireAdmin
        $this->assertMatchesRegularExpression(
            '/function index\(.*?\{[^}]*\$this->requireAdmin\(\)/s',
            $content,
            'MapVersionController::index must call requireAdmin()'
        );
    }

    public function test_version_show_has_require_admin(): void
    {
        $content = file_get_contents($this->base . '/src/Http/Controllers/MapVersionController.php');
        $this->assertMatchesRegularExpression(
            '/function show\(.*?\{[^}]*\$this->requireAdmin\(\)/s',
            $content,
            'MapVersionController::show must call requireAdmin()'
        );
    }

    public function test_version_show_uses_nullsafe_creator(): void
    {
        $content = file_get_contents($this->base . '/src/Http/Controllers/MapVersionController.php');
        $this->assertStringContainsString(
            "\$version->creator?->name",
            $content,
            'show() should use nullsafe operator on creator'
        );
    }


    // ── MapVersionService: scoped getVersion + whitelisted restore ──────

    public function test_get_version_scoped_by_map_id(): void
    {
        $content = file_get_contents($this->base . '/src/Services/MapVersionService.php');
        $this->assertStringContainsString(
            "where('map_id', \$map->id)->find(\$versionId)",
            $content,
            'getVersion must scope by map_id to prevent cross-map access'
        );
        $this->assertStringNotContainsString(
            'MapVersion::find($versionId)',
            $content,
            'getVersion must not use unscoped find()'
        );
    }

    public function test_restore_version_whitelists_node_fields(): void
    {
        $content = file_get_contents($this->base . '/src/Services/MapVersionService.php');
        $this->assertStringContainsString(
            "array_flip(['id', 'label', 'x', 'y', 'device_id', 'meta'])",
            $content,
            'restoreVersion must whitelist node fields via array_intersect_key'
        );
    }

    public function test_restore_version_whitelists_link_fields(): void
    {
        $content = file_get_contents($this->base . '/src/Services/MapVersionService.php');
        $this->assertStringContainsString(
            "array_flip(['id', 'src_node_id', 'dst_node_id', 'port_id_a', 'port_id_b', 'bandwidth_bps', 'style'])",
            $content,
            'restoreVersion must whitelist link fields via array_intersect_key'
        );
    }

    // ── MapService: meta guard, title/name guard, recursive merge, logged drops ──

    public function test_create_nodes_guards_meta_array(): void
    {
        $content = file_get_contents($this->base . '/src/Services/MapService.php');
        $this->assertStringContainsString(
            "is_array(\$nodeData['meta'] ?? null)",
            $content,
            'createNodes must guard meta with is_array check'
        );
    }

    public function test_update_map_properties_guards_title_with_trim(): void
    {
        $content = file_get_contents($this->base . '/src/Services/MapService.php');
        $this->assertTrue(
            str_contains($content, "is_string(\$data['title']) && trim(\$data['title']) !== ''"),
            'updateMapProperties must guard title with is_string + trim check'
        );
    }

    public function test_update_map_properties_guards_name_with_trim(): void
    {
        $content = file_get_contents($this->base . '/src/Services/MapService.php');
        $this->assertTrue(
            str_contains($content, "is_string(\$data['name']) && trim(\$data['name']) !== ''"),
            'updateMapProperties must guard name with is_string + trim check'
        );
    }

    public function test_update_map_properties_early_return_includes_name(): void
    {
        $content = file_get_contents($this->base . '/src/Services/MapService.php');
        // The early-return guard must include name, otherwise a request that only
        // updates name would skip the name update entirely.
        $this->assertStringContainsString(
            "!array_key_exists('name', \$data)",
            $content,
            'updateMapProperties early-return must check for name key, not just options and title'
        );
    }

    public function test_merge_map_options_uses_recursive_merge(): void
    {
        $content = file_get_contents($this->base . '/src/Services/MapService.php');

        // Should not use plain array_merge for the top-level merge
        $this->assertStringNotContainsString(
            "\$merged = array_merge(\$currentOptions, \$newOptions)",
            $content,
            'mergeMapOptions must not use plain array_merge for top-level options'
        );

        // Should iterate keys and merge nested arrays
        $this->assertStringContainsString('foreach ($newOptions as $key => $value)', $content);
    }

    public function test_create_links_logs_dropped_links(): void
    {
        $content = file_get_contents($this->base . '/src/Services/MapService.php');
        $this->assertStringContainsString(
            "Log::warning('WeathermapNG: dropped link",
            $content,
            'createLinks must log dropped links with unresolvable node references'
        );
    }

    // ── HealthController: no PID, genericized error messages ────────────

    public function test_live_endpoint_does_not_leak_pid(): void
    {
        $content = file_get_contents($this->base . '/src/Http/Controllers/HealthController.php');
        // The live() method should not include 'pid' => getmypid()
        $liveStart = strpos($content, 'public function live()');
        $this->assertNotFalse($liveStart, 'live() method must exist');
        $liveBlock = substr($content, $liveStart, 300);
        $this->assertStringNotContainsString('getmypid()', $liveBlock);
        $this->assertStringNotContainsString("'pid'", $liveBlock);
    }

    public function test_ready_endpoint_genericizes_error_message(): void
    {
        $content = file_get_contents($this->base . '/src/Http/Controllers/HealthController.php');
        $readyStart = strpos($content, 'public function ready()');
        $this->assertNotFalse($readyStart);
        $readyBlock = substr($content, $readyStart, 1200);

        // The response JSON must use a generic error, not the raw exception
        $responseStart = strpos($readyBlock, "'ready' => false");
        $this->assertNotFalse($responseStart, 'ready() must have an error response block');
        $responseBlock = substr($readyBlock, $responseStart, 200);
        $this->assertStringNotContainsString(
            "\$e->getMessage()",
            $responseBlock,
            'ready() response must not expose raw exception messages'
        );
        $this->assertStringContainsString("'Readiness check failed'", $responseBlock);
    }

    public function test_check_database_genericizes_error_message(): void
    {
        $content = file_get_contents($this->base . '/src/Http/Controllers/HealthController.php');
        $dbStart = strpos($content, 'private function checkDatabase()');
        $this->assertNotFalse($dbStart);
        $dbBlock = substr($content, $dbStart, 500);
        $this->assertStringNotContainsString(
            "'Database connection failed: ' . \$exception->getMessage()",
            $dbBlock,
            'checkDatabase() must not expose raw exception messages on unauthenticated endpoint'
        );
    }

    public function test_check_configuration_does_not_leak_api_token_status(): void
    {
        $content = file_get_contents($this->base . '/src/Http/Controllers/HealthController.php');
        $this->assertStringNotContainsString(
            'API token not configured',
            $content,
            'checkConfiguration() must not reveal whether an API token is configured'
        );
    }

    // ── RenderController: SSE max clamp ─────────────────────────────────

    public function test_sse_clamps_max_seconds(): void
    {
        $content = file_get_contents($this->base . '/src/Http/Controllers/RenderController.php');
        $this->assertStringContainsString(
            'min(600, max(5,',
            $content,
            'SSE endpoint must clamp max seconds to [5, 600]'
        );
        $this->assertStringNotContainsString(
            "\$maxSeconds = (int) \$request->get('max', 300);",
            $content,
            'SSE endpoint must not accept unbounded max parameter'
        );
    }

    public function test_sse_clamps_interval(): void
    {
        $content = file_get_contents($this->base . '/src/Http/Controllers/RenderController.php');
        $this->assertStringContainsString(
            'min(60, max(1,',
            $content,
            'SSE endpoint must clamp interval to [1, 60] seconds'
        );
        $this->assertStringNotContainsString(
            "\$interval = max(1, (int) \$request->get('interval', 5));",
            $content,
            'SSE endpoint must not accept unbounded interval parameter'
        );
    }

    public function test_stream_loop_sleeps_in_bounded_chunks(): void
    {
        $content = file_get_contents($this->base . '/src/Services/NodeDataService.php');
        $this->assertStringContainsString(
            "\$remaining = \$maxSeconds - (time() - \$start)",
            $content,
            'streamLoop must compute remaining time before sleeping'
        );
        $this->assertStringContainsString(
            'min($interval, max(1, $remaining))',
            $content,
            'streamLoop must sleep at most min(interval, remaining) seconds'
        );
    }

    // ── Node model: null guard + restricted columns ─────────────────────

    public function test_convert_status_to_string_guards_null(): void
    {
        $content = file_get_contents($this->base . '/src/Models/Node.php');
        $this->assertStringContainsString(
            "if (\$status === null)",
            $content,
            'convertStatusToString must guard against null status'
        );
        $this->assertStringContainsString(
            "return 'unknown'",
            $content,
            'convertStatusToString must return unknown for null status'
        );
    }

    public function test_convert_status_to_string_casts_to_string(): void
    {
        $content = file_get_contents($this->base . '/src/Models/Node.php');
        $this->assertStringContainsString(
            'strtolower((string) $status)',
            $content,
            'convertStatusToString must cast status to string before strtolower'
        );
    }

    public function test_fetch_device_restricts_eloquent_columns(): void
    {
        $content = file_get_contents($this->base . '/src/Models/Node.php');
        $this->assertStringContainsString(
            "->first(['device_id', 'hostname', 'status'])",
            $content,
            'fetchDevice must restrict Eloquent query to needed columns'
        );
        $this->assertStringNotContainsString(
            'Device::find($deviceId)',
            $content,
            'fetchDevice must not use unscoped find() which selects all columns'
        );
    }
    public function test_eloquent_device_uses_to_array_not_cast(): void
    {
        $content = file_get_contents($this->base . '/src/Models/Node.php');

        // (array) $device on an Eloquent model exposes protected props, not attributes.
        // Must use ->toArray() instead. Only the Eloquent branches (preloadDevices
        // and fetchDevice) need this; the DB::table stdClass path is fine with (array).
        $eloquentPreload = strpos($content, 'App\\Models\\Device::whereIn');
        $this->assertNotFalse($eloquentPreload, 'preloadDevices Eloquent branch must exist');
        $preloadBlock = substr($content, $eloquentPreload, 600);
        $this->assertStringContainsString('->toArray()', $preloadBlock);
        $this->assertStringNotContainsString('(array) $device', $preloadBlock);

        $eloquentFetch = strpos($content, "App\\Models\\Device::where('device_id'");
        $this->assertNotFalse($eloquentFetch, 'fetchDevice Eloquent branch must exist');
        $fetchBlock = substr($content, $eloquentFetch, 200);
        $this->assertStringContainsString('->toArray()', $fetchBlock);
        $this->assertStringNotContainsString('(array) $device', $fetchBlock);
    }
}
