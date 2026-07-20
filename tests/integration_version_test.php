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

$cleanup = function () {
    // Delete any temp maps and their versions
    $tempMaps = Map::where('name', 'LIKE', 'integration-test-map-%')->get();
    foreach ($tempMaps as $m) {
        MapVersion::where('map_id', $m->id)->delete();
        Link::where('map_id', $m->id)->delete();
        Node::where('map_id', $m->id)->delete();
        $m->delete();
    }
};

try {
    // Clean up any leftover temp maps from previous runs
    $cleanup();

    // Create a temporary map with minimal nodes/links
    $map = Map::create([
        'name' => 'integration-test-map-' . time(),
        'title' => 'Integration Test',
        'options' => ['width' => 800, 'height' => 600, 'background' => '#ffffff'],
    ]);
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

    $svc = app(MapVersionService::class);

    // --- Test 1: createVersion succeeds and sets created_at ---
    $v1 = $svc->createVersion($map, 'integration-test-1', 'first test', 1);
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
    assert_true($creator->username === 'admin', 'creator is the admin user');

    // --- Test 6: create second version and compare ---
    $v2 = $svc->createVersion($map, 'integration-test-2', 'second test', 1);
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
    $v3 = $svc->createVersion($map, 'integration-test-3', 'rollback test', 1);
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

    echo "\n";
    if (empty($failures)) {
        echo "All integration tests passed.\n";
        exit(0);
    } else {
        echo count($failures) . " failure(s):\n";
        foreach ($failures as $f) echo "  - $f\n";
        exit(1);
    }
} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
} finally {
    $cleanup();
}
