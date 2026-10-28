#!/usr/bin/env php
<?php
// Disposable LibreNMS stack: run through the real application, then restore each injected fault.
require '/opt/librenms/vendor/autoload.php';
$app = require '/opt/librenms/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require_once __DIR__ . '/../src/Services/InstallationReport.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
});

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use LibreNMS\Plugins\WeathermapNG\Services\InstallationReport;

function checkReport(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo "PASS: $message\n";
}

function reportPage(User $user)
{
    auth()->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    $session = app('session')->driver();
    $session->start();
    auth()->guard('web')->login($user);
    $session->save();
    $name = config('session.cookie');
    $encrypter = app('encrypter');
    $cookie = $encrypter->encrypt(CookieValuePrefix::create($name, $encrypter->getKey()) . $session->getId(), false);
    auth()->forgetGuards();
    $request = Request::create('http://localhost/plugin/WeathermapNG/diagnostics', 'GET', [], [$name => $cookie]);
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    return $response;
}

$root = base_path();
$plugin = sys_get_temp_dir() . '/wmng-report-' . bin2hex(random_bytes(6));
mkdir($plugin . '/output/maps', 0755, true);
mkdir($plugin . '/output/cache', 0755, true);
file_put_contents($plugin . '/VERSION', trim(file_get_contents(__DIR__ . '/../VERSION')));
$registryFile = $root . '/composer.plugins.json';
$oldRegistry = is_file($registryFile) ? file_get_contents($registryFile) : null;
$composerFile = $root . '/composer.json';
$oldComposer = file_get_contents($composerFile);
$db = app('db');
$connectionName = $db->getDefaultConnection();
$router = app('router');
$originalRoutes = $router->getRoutes();
$routeProperty = new ReflectionProperty($router, 'routes');
$urls = app('url');
$providers = $app->getLoadedProviders();
$providerProperty = new ReflectionProperty($app, 'loadedProviders');
$rows = $db->table('plugins')->where('plugin_name', 'WeathermapNG')->get();
$dataBefore = [];
foreach (['maps', 'nodes', 'links', 'map_templates', 'map_versions'] as $table) {
    $dataBefore[$table] = $db->table('wmng_' . $table)->orderBy('id')->get()->toJson();
}
$users = [];
$temporaryTable = false;

try {
    config(['librenms.install' => false, 'weathermapng.api_token' => 'report-secret-api',
        'weathermapng.security.embed_token' => 'report-secret-embed', 'weathermapng.webhooks.secret' => 'report-secret-hook']);
    $registry = json_decode($oldRegistry ?? '{}', true);
    $registry['require']['librenms/weathermapng'] = '*';
    file_put_contents($registryFile, json_encode($registry));
    $report = InstallationReport::collect($app, $root, $plugin);
    checkReport($report['ok'], 'healthy runtime, persistent registry, and writable fixture directories pass');
    checkReport($report['versions']['librenms'] !== 'unknown', 'LibreNMS version is reported');
    checkReport($report['authentication']['embed_token_configured'] === true, 'token configuration is a boolean');
    checkReport(!str_contains(json_encode($report), 'report-secret-'), 'configured API/embed/webhook secrets are absent');

    $composer = json_decode($oldComposer, true);
    unset($composer['require']['librenms/weathermapng']);
    file_put_contents($composerFile, json_encode($composer));
    checkReport(!InstallationReport::collect($app, $root, $plugin)['checks']['composer_declaration']['ok'], 'missing root declaration fails despite loaded plugin classes');
    file_put_contents($composerFile, $oldComposer);
    unlink($registryFile);
    checkReport(!InstallationReport::collect($app, $root, $plugin)['checks']['persistent_registry']['ok'], 'missing persistent registry is distinguished from runtime registration');
    file_put_contents($registryFile, json_encode($registry));

    $missingProvider = $providers;
    unset($missingProvider['LibreNMS\\Plugins\\WeathermapNG\\WeathermapNGProvider']);
    $providerProperty->setValue($app, $missingProvider);
    $report = InstallationReport::collect($app, $root, $plugin);
    checkReport(!$report['checks']['provider']['ok'] && $report['authentication']['embed_token_configured'] === null, 'missing provider fails with unknown authentication configuration');
    $providerProperty->setValue($app, $providers);

    $app->instance('router', new \Illuminate\Routing\Router(app('events'), $app));
    $report = InstallationReport::collect($app, $root, $plugin);
    checkReport(!$report['checks']['route_embed']['ok'] && !$report['checks']['route_json']['ok'], 'missing runtime routes fail independently of route source text');
    $app->instance('router', $router);

    $db->table('plugins')->where('plugin_name', 'WeathermapNG')->update(['plugin_active' => 0]);
    checkReport(!InstallationReport::collect($app, $root, $plugin)['checks']['plugin_enabled']['ok'], 'disabled plugin is not silently enabled');
    foreach ($rows as $row) {
        $db->table('plugins')->where('plugin_id', $row->plugin_id)->update(['plugin_active' => $row->plugin_active]);
    }

    chmod($plugin . '/output/maps', 0555);
    clearstatcache();
    checkReport(!InstallationReport::collect($app, $root, $plugin)['checks']['output/maps']['ok'], 'unwritable directory fails without repairing permissions');
    chmod($plugin . '/output/maps', 0755);
    rmdir($plugin . '/output/maps');
    InstallationReport::collect($app, $root, $plugin);
    checkReport(!is_dir($plugin . '/output/maps'), 'inspection never creates missing output directories');
    mkdir($plugin . '/output/maps');

    $db->statement('CREATE TEMPORARY TABLE wmng_maps (id INTEGER PRIMARY KEY)');
    $temporaryTable = true;
    checkReport(InstallationReport::collect($app, $root, $plugin)['counts']['maps'] === 0, 'a genuinely empty table reports zero');
    $db->statement('DROP TEMPORARY TABLE wmng_maps');
    $temporaryTable = false;

    $app->instance('db', new class($db) {
        public function __construct(private $database) {}
        public function connection() { return $this->database->connection(); }
        public function table($table) {
            if ($table === 'wmng_maps') {
                throw new RuntimeException('report-secret-table-access');
            }
            return $this->database->table($table);
        }
    });
    $report = InstallationReport::collect($app, $root, $plugin);
    checkReport($report['checks']['database']['ok'] && $report['counts']['maps'] === null, 'a table read failure stays unknown even with a working database connection');
    checkReport(!str_contains(json_encode($report), 'report-secret-'), 'table-access exception details are redacted');
    $app->instance('db', $db);

    $db->extend('report_failure', function () {
        throw new RuntimeException('report-secret-dsn password=report-secret-password');
    });
    config(['database.connections.report_failure' => ['driver' => 'report_failure']]);
    $db->setDefaultConnection('report_failure');
    $report = InstallationReport::collect($app, $root, $plugin);
    checkReport(!$report['checks']['database']['ok'] && $report['counts']['maps'] === null, 'database refusal reports unknown counts rather than an empty installation');
    checkReport(!str_contains(json_encode($report), 'report-secret-'), 'secret-bearing exceptions never enter the support report');
    $db->setDefaultConnection($connectionName);

    foreach ([true, false] as $admin) {
        $user = new User(['username' => 'wmng-report-' . bin2hex(random_bytes(6)), 'auth_type' => 'mysql', 'enabled' => true]);
        $user->setPassword(bin2hex(random_bytes(16)));
        $user->save();
        $users[] = $user;
        if ($admin) {
            $user->assignRole('admin');
        }
        $response = reportPage($user);
        checkReport($response->getStatusCode() === ($admin ? 200 : 403), 'HTTP diagnostics ' . ($admin ? 'accepts admins' : 'denies non-admins'));
        if ($admin) {
            $html = $response->getContent();
            checkReport(str_contains($html, 'id="installation-report"') && !str_contains($html, 'report-secret-'), 'admin report renders escaped JSON without configured secrets');
        }
    }

    auth()->guard('web')->setUser($users[0]);
    $missingIndex = new \Illuminate\Routing\RouteCollection();
    foreach ($originalRoutes->getRoutes() as $route) {
        if ($route->getName() !== 'weathermapng.index') {
            $missingIndex->add($route);
        }
    }
    $router->setRoutes($missingIndex);
    $urls->setRoutes($missingIndex);
    $view = app(\LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController::class)->diagnostics();
    checkReport($view->getData()['overallStatus'] === 'unhealthy', 'admin overall status includes failed installation checks');
    $response = reportPage($users[0]);
    checkReport($response->getStatusCode() === 200 && str_contains($response->getContent(), '<strong>Overall:</strong> Unhealthy'), 'HTTP admin page renders when the runtime index route is missing');
    $routeProperty->setValue($router, $originalRoutes);
    $urls->setRoutes($originalRoutes);

    $db->setDefaultConnection('report_failure');
    $view = app(\LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController::class)->diagnostics();
    $data = $view->getData();
    checkReport($data['overallStatus'] === 'unhealthy' && $data['stats']['maps'] === null, 'admin database refusal has unhealthy status and unknown counts');
    // Exercise the real diagnostic body without LibreNMS's database-backed navigation during the outage.
    $body = str_replace("@extends('layouts.librenmsv1')", '', file_get_contents(__DIR__ . '/../resources/views/diagnostics.blade.php'));
    $html = \Illuminate\Support\Facades\Blade::render($body . "\n@yield('content')", $data);
    checkReport(str_contains($html, 'Unknown') && !str_contains($html, 'report-secret-'), 'admin outage content displays Unknown and suppresses exception details');
    $db->setDefaultConnection($connectionName);
} finally {
    $app->instance('db', $db);
    $db->setDefaultConnection($connectionName);
    if ($temporaryTable) {
        $db->statement('DROP TEMPORARY TABLE wmng_maps');
    }
    $app->instance('router', $router);
    \Illuminate\Support\Facades\Route::swap($router);
    $routeProperty->setValue($router, $originalRoutes);
    $urls->setRoutes($originalRoutes);
    $providerProperty->setValue($app, $providers);
    file_put_contents($composerFile, $oldComposer);
    if ($oldRegistry === null) {
        @unlink($registryFile);
    } else {
        file_put_contents($registryFile, $oldRegistry);
    }
    foreach ($rows as $row) {
        $db->table('plugins')->where('plugin_id', $row->plugin_id)->update(['plugin_active' => $row->plugin_active]);
    }
    foreach ($users as $user) {
        $user->delete();
    }
    chmod($plugin . '/output/maps', 0755);
    rmdir($plugin . '/output/maps');
    rmdir($plugin . '/output/cache');
    rmdir($plugin . '/output');
    unlink($plugin . '/VERSION');
    rmdir($plugin);
}
foreach ($dataBefore as $table => $before) {
    checkReport($db->table('wmng_' . $table)->orderBy('id')->get()->toJson() === $before, "$table content is unchanged");
}
