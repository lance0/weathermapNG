#!/usr/bin/env php
<?php
// Load the collector directly: missing package discovery is itself a diagnostic case.
require_once __DIR__ . '/src/Services/InstallationReport.php';

if (array_diff(array_slice($argv, 1), ['--json'])) {
    fwrite(STDERR, "Usage: php verify-deployment.php [--json]\n");
    exit(2);
}
$root = getenv('LIBRENMS_PATH') ?: '/opt/librenms';
$app = null;
ob_start();
try {
    if (!is_file($root . '/vendor/autoload.php') || !is_file($root . '/bootstrap/app.php')) {
        throw new RuntimeException('LibreNMS bootstrap unavailable');
    }
    require_once $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
} catch (Throwable $e) {
    $app = null;
} finally {
    ob_end_clean();
}
$report = LibreNMS\Plugins\WeathermapNG\Services\InstallationReport::collect($app, $root, __DIR__);
if (in_array('--json', $argv, true)) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "WeathermapNG installation report\n";
    foreach ($report['checks'] as $name => $check) {
        echo ($check['ok'] ? 'PASS ' : 'FAIL ') . $name . ($check['recovery'] ? ': ' . $check['recovery'] : '') . "\n";
    }
    echo $report['observation'] . "\n";
}
exit($report['ok'] ? 0 : 1);
