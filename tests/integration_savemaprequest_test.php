#!/usr/bin/env php
<?php
/**
 * Container integration test for SaveMapRequest::sanitize().
 *
 * Run inside the librenms-dev container:
 *   docker exec -u librenms librenms-dev php \
 *     /opt/librenms/html/plugins/WeathermapNG/tests/integration_savemaprequest_test.php
 *
 * SaveMapRequest extends FormRequest (illuminate/foundation), which isn't
 * installed in the standalone test bootstrap. This test bootstraps the real
 * Laravel app and exercises sanitize() directly — the standalone
 * SaveMapRequestSanitizeTest skips in that env and delegates here.
 *
 * Exits 0 on success, 1 on failure.
 */

require '/opt/librenms/vendor/autoload.php';
$app = require '/opt/librenms/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use LibreNMS\Plugins\WeathermapNG\Http\Requests\SaveMapRequest;

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

function assert_throws(callable $fn, string $exceptionClass, string $msg): void {
    global $failures;
    try {
        $fn();
        $failures[] = "$msg (expected $exceptionClass)";
        echo "FAIL: $msg (expected $exceptionClass)\n";
    } catch (\Throwable $e) {
        if ($e instanceof $exceptionClass) {
            echo "PASS: $msg\n";
        } else {
            $failures[] = "$msg (got " . get_class($e) . " not $exceptionClass)";
            echo "FAIL: $msg (got " . get_class($e) . " not $exceptionClass)\n";
        }
    }
}

$request = new SaveMapRequest();

// Normal label: strips tags and trims.
$result = $request->sanitize([
    'nodes' => [
        ['id' => 1, 'label' => '  <b>Core Router</b>  ', 'x' => 10, 'y' => 20],
    ],
]);
assert_true($result['nodes'][0]['label'] === 'Core Router', 'normal label is stripped and trimmed');

// HTML-only label throws (the invariant SaveMapRequest must not silently
// revert to inline strip_tags — that would persist '' instead of rejecting).
assert_throws(
    function () use ($request) {
        $request->sanitize(['nodes' => [['id' => 1, 'label' => '<b></b>', 'x' => 10, 'y' => 20]]]);
    },
    \InvalidArgumentException::class,
    'html-only label throws InvalidArgumentException'
);

// Empty string label throws.
assert_throws(
    function () use ($request) {
        $request->sanitize(['nodes' => [['id' => 1, 'label' => '', 'x' => 10, 'y' => 20]]]);
    },
    \InvalidArgumentException::class,
    'empty string label throws InvalidArgumentException'
);

// Script-only label throws.
assert_throws(
    function () use ($request) {
        $request->sanitize(['nodes' => [['id' => 1, 'label' => '<script></script>', 'x' => 10, 'y' => 20]]]);
    },
    \InvalidArgumentException::class,
    'script-only label throws InvalidArgumentException'
);

// null label passes through (nullable|string rule).
$result = $request->sanitize([
    'nodes' => [
        ['id' => 1, 'label' => null, 'x' => 10, 'y' => 20],
    ],
]);
assert_true($result['nodes'][0]['label'] === null, 'null label passes through unchanged');

// Missing label key passes through.
$result = $request->sanitize([
    'nodes' => [
        ['id' => 1, 'x' => 10, 'y' => 20],
    ],
]);
assert_true(!array_key_exists('label', $result['nodes'][0]), 'missing label key passes through');

// A bad label in any node throws (bulk invariant — MapController::save
// catches → 422; MapService::saveMap runs in a DB transaction).
assert_throws(
    function () use ($request) {
        $request->sanitize([
            'nodes' => [
                ['id' => 1, 'label' => 'Router', 'x' => 10, 'y' => 20],
                ['id' => 2, 'label' => '<b></b>', 'x' => 30, 'y' => 40],
            ],
        ]);
    },
    \InvalidArgumentException::class,
    'html-only label in any node of a bulk save throws'
);

echo "\n" . (empty($failures)
    ? "ALL TESTS PASSED (0 failures)\n"
    : count($failures) . " TEST(S) FAILED\n");
exit(empty($failures) ? 0 : 1);
