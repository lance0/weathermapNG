#!/usr/bin/env php
<?php
/**
 * Container integration test: node-label normalization invariant.
 *
 * Run inside the librenms-dev container:
 *   docker exec -u librenms librenms-dev php \
     /opt/librenms/html/plugins/WeathermapNG/tests/integration_node_label_invariant_test.php
 *
 * Exercises the node-label normalization invariant end-to-end through the
 * real HTTP kernel (FormRequest validation and route binding run): all
 * node write paths — single-node create, single-node update, and bulk
 * save — must reject labels empty after strip_tags(trim()) with 422, and
 * must normalize valid labels identically. Also confirms the dead-code
 * removals (CreateLinkRequest, CreateNodeRequest, MapCacheService,
 * autoSave) stay removed.
 *
 * Middleware is swapped in-process only: VerifyCsrfToken → passthrough,
 * the `auth` alias → an injector that sets the admin user on the guard
 * per request (no real browser session needed).
 *
 * Fixtures are collision-safe (unique map name per run); cleanup deletes
 * by tracked map ID in the finally block.
 *
 * Exits 0 on success, 1 on failure.
 */

require '/opt/librenms/vendor/autoload.php';
$app = require '/opt/librenms/bootstrap/app.php';

use Illuminate\Http\Request;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\Services\NodeLabelNormalizer;

// Swap middleware so synthetic kernel requests authenticate without a
// real browser session: VerifyCsrfToken → passthrough (no 419), and the
// `auth` alias → an injector that sets the admin user on the guard for
// each request (so auth()->user() resolves inside requireAdmin()).
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->forgetInstance(\Illuminate\Contracts\Http\Kernel::class);
$app->bind(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, fn () => new class { public function handle($r, $n) { return $n($r); } });
$app->bind(\Illuminate\Auth\Middleware\Authenticate::class, function () {
    return new class {
        public function handle($request, $next) {
            $guard = auth()->guard('web');
            if (method_exists($guard, 'setUser')) {
                $guard->setUser(\App\Models\User::find(1));
            }
            return $next($request);
        }
    };
});

$failures = [];
function pass(string $msg): void { echo "PASS: $msg\n"; }
function fail(string $msg): void { global $failures; $failures[] = $msg; echo "FAIL: $msg\n"; }
function check($cond, string $msg): void { ($cond ? 'pass' : 'fail')($msg); }

/** Dispatch a request through the HTTP kernel with an authenticated admin. */
function dispatch(string $method, string $uri, array $body = null): array {
    $app = app();
    $request = Request::create("http://localhost$uri", $method,
        $body ?? [], [], [], [
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
        ]);
    $request->headers->set('Accept', 'application/json');
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();
    $content = $response->getContent();
    $json = json_decode($content, true);
    return [$status, $json ?: $content, $response];
}

$mapIdsToDelete = [];

try {
    // Unique-per-run map name so a failed prior run can't adopt or delete
    // unrelated records. Cleanup deletes by tracked map ID only.
    $runTag = bin2hex(random_bytes(4));
    $mapName = "smoke-test-$runTag-map";

    // ----- PR #27: dead FormRequest removal sanity -----
    check(!class_exists(\LibreNMS\Plugins\WeathermapNG\Http\Requests\CreateLinkRequest::class),
        'CreateLinkRequest class removed (PR #27)');
    check(!class_exists(\LibreNMS\Plugins\WeathermapNG\Http\Requests\CreateNodeRequest::class),
        'CreateNodeRequest class removed (PR #27)');
    check(!class_exists(\LibreNMS\Plugins\WeathermapNG\Services\MapCacheService::class),
        'MapCacheService class removed (PR #27)');
    check(class_exists(NodeLabelNormalizer::class), 'NodeLabelNormalizer class exists (PR #28)');

    // ----- PR #28: shared NodeLabelNormalizer real behavior -----
    check(NodeLabelNormalizer::normalize('  <b>Core</b>  ') === 'Core', 'normalize strips tags and trims');
    check(NodeLabelNormalizer::normalize(null) === '', 'normalize(null) returns empty string');
    try { NodeLabelNormalizer::normalizeOrThrow('<b></b>'); fail('normalizeOrThrow("<b></b>") should throw'); }
    catch (\InvalidArgumentException $e) { pass('normalizeOrThrow("<b></b>") throws InvalidArgumentException'); }

    // ----- Create a test map through the kernel (POST /plugin/WeathermapNG/map) -----
    [$status, $json] = dispatch('POST', '/plugin/WeathermapNG/map', [
        'name' => $mapName,
        'title' => 'Smoke Test Today',
    ]);
    $mapId = is_array($json) ? ($json['id'] ?? $json['map']['id'] ?? null) : null;
    if (!$mapId) {
        fail("create map returned $status: " . (is_string($json) ? substr($json, 0, 200) : json_encode($json)));
        throw new \RuntimeException('cannot continue without a map');
    }
    $mapIdsToDelete[] = $mapId;
    pass("created test map id=$mapId via kernel (status=$status)");

    // ----- PR #28: single-node create with HTML-only label → 422 -----
    [$status, $json] = dispatch('POST', "/plugin/WeathermapNG/map/$mapId/node", [
        'label' => '<b></b>', 'x' => 10, 'y' => 20,
    ]);
    check($status === 422, "single-node create with <b></b> label → 422 (got $status)");

    // ----- PR #28: single-node create with normal label → 2xx + normalized -----
    [$status, $json] = dispatch('POST', "/plugin/WeathermapNG/map/$mapId/node", [
        'label' => '  <b>Router-A</b>  ', 'x' => 10, 'y' => 20,
    ]);
    check(in_array($status, [200, 201], true), "single-node create with normal label → 2xx (got $status)");
    $nodeAId = is_array($json) ? ($json['id'] ?? $json['node']['id'] ?? null) : null;
    if (!$nodeAId) {
        $nodeAId = Node::where('map_id', $mapId)->where('label', 'Router-A')->value('id');
    }
    check($nodeAId !== null, "created node A (id=$nodeAId) and got non-null id");
    check(Node::where('map_id', $mapId)->where('label', 'Router-A')->exists(),
        'single-node label normalized to "Router-A" in DB');

    // ----- PR #28: single-node update with HTML-only label → 422 -----
    [$status, $json] = dispatch('PATCH', "/plugin/WeathermapNG/map/$mapId/node/$nodeAId", [
        'label' => '<i></i>',
    ]);
    check($status === 422, "single-node update with <i></i> label → 422 (got $status)");
    // Verify the bad update did not change the stored label.
    check(Node::where('id', $nodeAId)->where('label', 'Router-A')->exists(),
        'failed update left stored label unchanged ("Router-A")');

    // ----- PR #28: single-node update with valid HTML label → 2xx + normalized -----
    [$status, $json] = dispatch('PATCH', "/plugin/WeathermapNG/map/$mapId/node/$nodeAId", [
        'label' => '  <b>Router-A-Updated</b>  ',
    ]);
    check(in_array($status, [200, 201], true), "single-node update with valid label → 2xx (got $status)");
    check(Node::where('id', $nodeAId)->where('label', 'Router-A-Updated')->exists(),
        'update normalized label to "Router-A-Updated" in DB');

    // ----- PR #28/#29: bulk save with empty-after-strip label → 422 -----
    [$status, $json] = dispatch('POST', "/plugin/WeathermapNG/api/maps/$mapId/save", [
        'nodes' => [
            ['id' => $nodeAId, 'label' => 'Router-A', 'x' => 10, 'y' => 20],
            ['label' => '<script></script>', 'x' => 30, 'y' => 40],
        ],
        'links' => [],
    ]);
    check($status === 422, "bulk save with <script></script> label → 422 (got $status)");

    // ----- PR #28/#29: bulk save with all-valid labels → 2xx -----
    [$status, $json] = dispatch('POST', "/plugin/WeathermapNG/api/maps/$mapId/save", [
        'nodes' => [
            ['label' => '  <b>Router-B</b>  ', 'x' => 100, 'y' => 200],
            ['label' => 'Router-C', 'x' => 300, 'y' => 400],
        ],
        'links' => [],
    ]);
    check(in_array($status, [200, 201], true), "bulk save with valid labels → 2xx (got $status)");
    check(is_array($json) && ($json['success'] ?? false) === true, 'bulk save returned success:true');

    check(Node::where('map_id', $mapId)->where('label', 'Router-B')->exists(),
        'bulk-saved label "Router-B" normalized (stripped/trimmed)');
    check(Node::where('map_id', $mapId)->where('label', 'Router-C')->exists(),
        'bulk-saved label "Router-C" preserved');

    // ----- PR #26: auto-save route/method/config gone -----
    $hasAutoSave = false;
    foreach (app('router')->getRoutes() as $r) {
        if (str_contains($r->getActionName() ?? '', 'autoSave')) { $hasAutoSave = true; break; }
    }
    check(!$hasAutoSave, 'autoSave route removed (PR #26)');
    check(!method_exists(\LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapVersionController::class, 'autoSave'),
        'MapVersionController::autoSave method removed (PR #26)');
    check(is_null(config('weathermapng.auto_save')) && is_null(config('weathermapng.auto_save_interval')),
        'auto_save config keys removed (PR #26)');

    // ----- PR #25: debug-gated logging config key + embed view formatting -----
    check(array_key_exists('debug', config('weathermapng') ?? []), 'weathermapng.debug config key exists (PR #25)');
    $embedView = file_get_contents('/opt/librenms/html/plugins/WeathermapNG/resources/views/embed.blade.php');
    check(str_contains($embedView, 'humanBits') && str_contains($embedView, 'Σ'),
        'embed view uses humanBits AND Σ for node rate label (PR #25)');

} finally {
    // Cleanup: delete tracked test maps and their nodes/links (by ID).
    foreach ($mapIdsToDelete as $id) {
        if ($id) {
            Link::where('map_id', $id)->delete();
            Node::where('map_id', $id)->delete();
            Map::where('id', $id)->delete();
            echo "CLEANUP: deleted map id=$id and its nodes/links\n";
        }
    }
}

echo "\n" . (empty($failures)
    ? "ALL SMOKE TESTS PASSED (0 failures)\n"
    : count($failures) . " TEST(S) FAILED\n");
exit(empty($failures) ? 0 : 1);
