#!/usr/bin/env php
<?php
/**
 * Container integration test for MapVersionService.
 *
 * Run inside the librenms-dev container:
 *   docker exec -u librenms librenms-dev php \
 *     /opt/librenms/html/plugins/WeathermapNG/tests/integration_version_test.php
 *
 * Creates a temporary map with minimal nodes/links, runs all tests
 * against it, then cleans up. Exits 0 on success, 1 on failure.
 */

require '/opt/librenms/vendor/autoload.php';
$app = require '/opt/librenms/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\MapVersion;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\Services\MapVersionService;
use Illuminate\Support\Collection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LibreNMS\Plugins\WeathermapNG\Services\WebhookDispatcher;

$failures = [];

function assert_true($cond, string $msg): void {
    global $failures;
    if (!$cond) {
        $failures[] = $msg;
        echo "FAIL: $msg\n";
    } else {
        echo "PASS: $msg\n";
    }
}

$ownedMapIds = [];
$ownedUser = null;
$cleanup = function () use (&$ownedMapIds, &$ownedUser) {
    foreach ($ownedMapIds as $id) {
        MapVersion::where('map_id', $id)->delete();
        Link::where('map_id', $id)->delete();
        Node::where('map_id', $id)->delete();
        Map::whereKey($id)->delete();
    }
    $ownedUser?->delete();
};

try {
    $ownedUser = new User(['username' => 'wmng-version-' . bin2hex(random_bytes(8)), 'auth_type' => 'mysql', 'enabled' => true]);
    $ownedUser->setPassword(bin2hex(random_bytes(16)));
    $ownedUser->save();

    // Create a temporary map with minimal nodes/links
    $map = Map::create([
        'name' => 'integration-test-map-' . bin2hex(random_bytes(8)),
        'title' => 'Integration Test',
        'options' => ['width' => 800, 'height' => 600, 'background' => '#ffffff'],
    ]);
    $ownedMapIds[] = $map->id;
    echo "Created temp map id={$map->id}\n";

    // Create two nodes and a link between them
    $node1 = Node::create([
        'map_id' => $map->id,
        'label' => 'TestNode1',
        'x' => 100,
        'y' => 100,
    ]);
    $node2 = Node::create([
        'map_id' => $map->id,
        'label' => 'TestNode2',
        'x' => 300,
        'y' => 300,
    ]);
    $link = Link::create([
        'map_id' => $map->id,
        'src_node_id' => $node1->id,
        'dst_node_id' => $node2->id,
    ]);

    // Count delivery attempts without contacting configured external endpoints.
    $webhooks = new class extends WebhookDispatcher {
        public int $calls = 0;
        public function dispatch(string $event, array $map): void { $this->calls++; }
    };
    $svc = new MapVersionService($webhooks);

    // --- Test 1: createVersion succeeds and sets created_at ---
    $v1 = $svc->createVersion($map, 'integration-test-1', 'first test', $ownedUser->user_id);
    assert_true($v1 instanceof MapVersion, 'createVersion returns MapVersion instance');
    assert_true($v1->id > 0, 'createVersion assigns an id');
    assert_true($v1->created_at !== null, 'createVersion sets created_at');
    assert_true($v1->name === 'integration-test-1', 'createVersion preserves name');

    // --- Test 2: config_snapshot is a valid array with nodes/links ---
    $snapshot = $v1->config_snapshot;
    assert_true(is_array($snapshot), 'config_snapshot is cast to array');
    assert_true(isset($snapshot['nodes']), 'snapshot contains nodes');
    assert_true(isset($snapshot['links']), 'snapshot contains links');
    assert_true(count($snapshot['nodes']) === 2, 'snapshot has 2 nodes');
    assert_true(count($snapshot['links']) === 1, 'snapshot has 1 link');

    // --- Test 3: getVersions returns a Collection containing our version ---
    $versions = $svc->getVersions($map, 20);
    assert_true($versions instanceof Collection, 'getVersions returns a Collection (not a Builder)');
    assert_true($versions->contains('id', $v1->id), 'getVersions includes the created version');
    assert_true($versions->count() >= 1, 'getVersions returns at least 1 version');

    // --- Test 4: no updated_at column written ---
    // If UPDATED_AT wasn't null, the insert would have failed with "Unknown column 'updated_at'"
    // The fact that createVersion succeeded proves this. Verify via model inspection.
    assert_true($v1->getUpdatedAtColumn() === null || (new \ReflectionClass(MapVersion::class))->getConstant('UPDATED_AT') === null,
        'MapVersion has UPDATED_AT = null (no updated_at writes)');

    // --- Test 5: creator relation works with user_id ---
    $creator = $v1->creator;
    assert_true($creator !== null, 'creator relation resolves to a User');
    assert_true($creator?->user_id === $ownedUser->user_id && $creator?->username === $ownedUser->username, 'creator resolves to the owned fixture user');

    // --- Test 6: create second version and compare ---
    $v2 = $svc->createVersion($map, 'integration-test-2', 'second test', $ownedUser->user_id);
    $diff = $svc->compareVersions($v1, $v2);
    assert_true(is_array($diff), 'compareVersions returns an array');
    assert_true(isset($diff['nodes_added']), 'diff has nodes_added');
    assert_true(isset($diff['nodes_removed']), 'diff has nodes_removed');
    assert_true(isset($diff['nodes_modified']), 'diff has nodes_modified');
    assert_true(isset($diff['links_added']), 'diff has links_added');
    assert_true(isset($diff['links_removed']), 'diff has links_removed');
    assert_true(isset($diff['links_modified']), 'diff has links_modified');

    // --- Test 7: deleteVersion removes the version ---
    $svc->deleteVersion($v1);
    $svc->deleteVersion($v2);
    $remaining = $svc->getVersions($map, 20);
    assert_true(!$remaining->contains('id', $v1->id), 'deleteVersion removes v1');
    assert_true(!$remaining->contains('id', $v2->id), 'deleteVersion removes v2');

    // --- Test 8: restoreVersion creates a true rollback ---
    $v3 = $svc->createVersion($map, 'integration-test-3', 'rollback test', $ownedUser->user_id);
    $nodeCountBefore = $map->nodes()->count();
    $linkCountBefore = $map->links()->count();

    // Modify the map: add a node
    $node3 = Node::create([
        'map_id' => $map->id,
        'label' => 'ExtraNode',
        'x' => 500,
        'y' => 500,
    ]);
    assert_true($map->nodes()->count() === $nodeCountBefore + 1, 'node added before restore');

    // Restore should bring us back to the snapshot state
    $svc->restoreVersion($v3);
    $map->refresh();
    $nodeCountAfter = $map->nodes()->count();
    $linkCountAfter = $map->links()->count();
    assert_true($nodeCountAfter === $nodeCountBefore, 'restoreVersion removes added nodes (true rollback)');
    assert_true($linkCountAfter === $linkCountBefore, 'restoreVersion preserves link count');
    $svc->deleteVersion($v3);

    // --- Test 9: reject corrupt snapshots without graph or history mutation ---
    $state = function () use ($map): array {
        return [
            (array) DB::table('wmng_maps')->where('id', $map->id)->first(),
            DB::table('wmng_nodes')->where('map_id', $map->id)->orderBy('id')->get()->toJson(),
            DB::table('wmng_links')->where('map_id', $map->id)->orderBy('id')->get()->toJson(),
            DB::table('wmng_map_versions')->where('map_id', $map->id)->orderBy('id')->get()->toJson(),
        ];
    };
    $invalid = MapVersion::create(['map_id' => $map->id, 'name' => 'Invalid', 'config_snapshot' => []]);
    $before = $state();
    $calls = $webhooks->calls;
    $rejected = false;
    try {
        $svc->restoreVersion($invalid);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }
    assert_true($rejected && $before === $state(), 'empty root is rejected without graph/history changes');
    assert_true($calls === $webhooks->calls, 'rejected restore emits no notifications');

    // --- Test 10: complete configuration and cached relations ---
    $map->load(['nodes', 'links']);
    $map->update(['description' => 'Historical description', 'options' => ['tags' => ['historical'], 'default_node_style' => ['shape' => 'rect']]]);
    $node1->update(['meta' => ['icon' => 'router', 'sub_map_id' => null]]);
    $saved = $svc->createVersion($map, 'Complete');
    $savedSnapshot = $saved->config_snapshot;
    assert_true($savedSnapshot['nodes'][$node1->id]['meta']['icon'] === 'router', 'snapshot captures fresh node relations');
    $map->update(['title' => 'Current title', 'description' => null, 'options' => ['tags' => ['current'], 'extra' => true]]);
    $node1->update(['label' => 'Current label']);
    $current = $svc->createVersion($map, 'Current')->config_snapshot;
    $count = MapVersion::where('map_id', $map->id)->count();
    $restored = $svc->restoreVersion($saved);
    $history = MapVersion::versions($map->id)->get();
    assert_true($restored->title === $savedSnapshot['map']['title'], 'restore returns fresh map fields');
    assert_true($restored->options === $savedSnapshot['map']['options'], 'complete restore replaces all options');
    assert_true(MapVersion::where('map_id', $map->id)->count() === $count + 2, 'restore retains old history and adds two rows');
    assert_true($history[0]->config_snapshot === $savedSnapshot, 'post-restore row captures restored graph');
    assert_true($history[1]->config_snapshot === $current, 'pre-restore checkpoint captures current graph');
    assert_true($saved->fresh()->config_snapshot === $savedSnapshot, 'target history remains unchanged');
    $svc->restoreVersion($history[1]);
    assert_true($svc->getLatestVersion($map)->config_snapshot === $current, 'checkpoint can recover preceding configuration');

    // --- Test 11: failure after both new history rows were inserted rolls back ---
    $injectFailure = true;
    MapVersion::created(function (MapVersion $created) use ($map, &$injectFailure) {
        if ($injectFailure && (int) $created->map_id === (int) $map->id && str_starts_with($created->name ?? '', 'Restored version #')) {
            throw new RuntimeException('injected restore history failure');
        }
    });
    $before = $state();
    $calls = $webhooks->calls;
    $failed = false;
    try {
        $svc->restoreVersion($saved);
    } catch (RuntimeException $e) {
        $failed = $e->getMessage() === 'injected restore history failure';
    } finally {
        $injectFailure = false;
    }
    assert_true($failed && $before === $state(), 'post-history failure rolls back graph, map, and both history rows');
    assert_true($calls === $webhooks->calls, 'failed restore emits no notifications');

    // --- Test 12: nested transactions defer notifications until real commit ---
    $before = $state();
    $calls = $webhooks->calls;
    DB::beginTransaction();
    $svc->restoreVersion($saved);
    assert_true($calls === $webhooks->calls, 'nested restore does not notify before outer commit');
    DB::rollBack();
    assert_true($before === $state() && $calls === $webhooks->calls, 'outer rollback restores all rows and emits zero notifications');
    DB::beginTransaction();
    $svc->restoreVersion($saved);
    DB::commit();
    assert_true($calls + 2 === $webhooks->calls, 'outer commit dispatches both saved-version notifications');

    // --- Test 13: same-second ordering and deliberate empty graph ---
    MapVersion::where('map_id', $map->id)->update(['created_at' => '2026-01-01 00:00:00']);
    $newestId = MapVersion::where('map_id', $map->id)->max('id');
    assert_true($svc->getLatestVersion($map)->id === $newestId, 'latest breaks timestamp ties with descending id');
    $empty = MapVersion::create(['map_id' => $map->id, 'name' => 'Empty', 'config_snapshot' => ['map' => ['id' => $map->id], 'nodes' => [], 'links' => []]]);
    $svc->restoreVersion($empty);
    assert_true($map->nodes()->count() === 0 && $map->links()->count() === 0, 'well-shaped empty graph remains valid');
    $checkpoint = MapVersion::versions($map->id)->get()[1];
    $svc->restoreVersion($checkpoint);
    assert_true($map->nodes()->count() === 2 && $map->links()->count() === 1, 'checkpoint recovers graph after deliberate empty restore');

    echo "\n";
    if (empty($failures)) {
        echo "All integration tests passed.\n";
        $exitCode = 0;
    } else {
        echo count($failures) . " failure(s):\n";
        foreach ($failures as $f) echo "  - $f\n";
        $exitCode = 1;
    }
} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    $exitCode = 1;
} finally {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    $cleanup();
}

exit($exitCode);
