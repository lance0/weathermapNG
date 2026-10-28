<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

class InstallationReport
{
    public static function collect(?object $app, string $root, string $plugin): array
    {
        $report = [
            'versions' => ['plugin' => trim((string) @file_get_contents($plugin . '/VERSION')), 'php' => PHP_VERSION],
            'deployment' => is_file('/.dockerenv') ? 'docker' : 'native',
            'runtime' => ['sapi' => PHP_SAPI, 'uid' => function_exists('posix_geteuid') ? posix_geteuid() : null],
            'checks' => [], 'counts' => [],
            'authentication' => ['session_requires_login' => true, 'embed_token_configured' => null],
            'observation' => 'Runtime checks only; authenticated HTTP access and web PHP/opcode cache freshness are not verified.',
        ];
        $check = function ($name, $ok, $recovery) use (&$report) {
            $report['checks'][$name] = ['ok' => $ok, 'recovery' => $ok ? null : $recovery];
        };
        $rootCommand = 'As the LibreNMS user: cd ' . escapeshellarg($root) . ' && ';
        $installCommand = 'As the LibreNMS user: cd ' . escapeshellarg($plugin) . ' && ./quick-install.sh';
        foreach (['gd', 'json', 'pdo', 'mbstring'] as $extension) {
            $check('extension_' . $extension, extension_loaded($extension), 'Install the PHP ' . $extension . ' extension for the LibreNMS PHP runtime.');
        }
        $check('bootstrap', $app !== null, $rootCommand . 'php artisan --version; check runtime dependencies if bootstrap fails');
        foreach (['composer.json' => 'composer_declaration', 'composer.plugins.json' => 'persistent_registry'] as $file => $name) {
            $data = json_decode((string) @file_get_contents($root . '/' . $file), true);
            $check($name, isset($data['require']['librenms/weathermapng']), $installCommand);
        }
        $provider = false;
        try {
            $provider = $app && ($app->getLoadedProviders()['LibreNMS\\Plugins\\WeathermapNG\\WeathermapNGProvider'] ?? false);
        } catch (\Throwable $e) {}
        $check('provider', (bool) $provider, $rootCommand . 'php artisan package:discover');
        $database = false;
        try {
            if ($app) $database = (bool) $app->make('db')->connection()->getPdo();
        } catch (\Throwable $e) {}
        $check('database', $database, 'Check the selected LibreNMS database configuration and connectivity; credentials are not included in this report.');
        try {
            $enabled = $database && $app && $app->make('db')->table('plugins')->where('plugin_name', 'WeathermapNG')->where('version', 2)->where('plugin_active', 1)->exists();
            $check('plugin_enabled', (bool) $enabled, $rootCommand . './lnms plugin:enable WeathermapNG');
        } catch (\Throwable $e) {
            $check('plugin_enabled', false, 'Check database connectivity and plugin registration.');
        }
        $requiredColumns = [
            'maps' => ['id','name','title','description','parent_map_id','width','height','options','created_at','updated_at'],
            'nodes' => ['id','map_id','label','x','y','device_id','meta','created_at','updated_at'],
            'links' => ['id','map_id','src_node_id','dst_node_id','port_id_a','port_id_b','bandwidth_bps','style','created_at','updated_at'],
            'map_templates' => ['id','name','title','description','width','height','config','icon','category','is_built_in','created_at','updated_at'],
            'map_versions' => ['id','map_id','name','description','config_snapshot','created_by','created_at'],
        ];
        foreach ($requiredColumns as $table => $columns) {
            $report['counts'][$table] = null;
            try {
                if ($database) $report['counts'][$table] = $app->make('db')->table('wmng_' . $table)->count();
            } catch (\Throwable $e) {
                // Counts remain unknown when a database or table cannot be read.
            }
            $check('table_' . $table, $report['counts'][$table] !== null, $database ? 'Check table permissions and schema. For missing tables, as the LibreNMS user: cd ' . escapeshellarg($plugin) . ' && php database/setup.php' : 'Restore database connectivity before checking whether this table exists.');
            $schema = false;
            try {
                if ($database) {
                    // Query the actual runtime table (including temporary shadows), without reading rows.
                    $app->make('db')->table('wmng_' . $table)
                        ->select(array_map(fn($column) => 'wmng_' . $table . '.' . $column, $columns))->limit(0)->get();
                    $schema = true;
                }
            } catch (\Throwable $e) {}
            $check('schema_' . $table, $schema, $database
                ? 'Check required table columns and read permissions. ' . $installCommand
                : 'Restore database connectivity before checking required table columns.');
        }
        foreach (['index','editor','embed','json','live','sse','diagnostics'] as $route) {
            try {
                $ok = $app && $app->make('router')->getRoutes()->getByName('weathermapng.' . $route) !== null;
            } catch (\Throwable $e) { $ok = false; }
            $check('route_' . $route, (bool) $ok, $rootCommand . 'php artisan package:discover && php artisan route:clear; check web PHP/opcode cache separately');
        }
        foreach (['output/maps','output/cache'] as $path) {
            $check($path, is_dir($plugin . '/' . $path) && is_writable($plugin . '/' . $path), 'Create the output directory during installation and give the LibreNMS runtime user write access.');
        }
        $report['cache'] = ['routes' => null, 'configuration' => null];
        $report['versions']['librenms'] = 'unknown';
        try {
            if ($app) {
                $report['cache'] = ['routes' => $app->routesAreCached(), 'configuration' => $app->configurationIsCached()];
                if (class_exists('LibreNMS\\Util\\Version')) {
                    $report['versions']['librenms'] = \LibreNMS\Util\Version::VERSION;
                }
                if ($provider) {
                    $report['authentication']['embed_token_configured'] = !empty($app->make('config')->get('weathermapng.security.embed_token'));
                }
            }
        } catch (\Throwable $e) {
            $check('runtime_configuration', false, 'Check LibreNMS bootstrap and configuration; no exception details are included.');
        }
        $report['ok'] = !in_array(false, array_column($report['checks'], 'ok'), true);
        return $report;
    }
}
