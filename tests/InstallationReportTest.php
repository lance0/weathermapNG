<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\Services\InstallationReport;
use PHPUnit\Framework\TestCase;

class InstallationReportTest extends TestCase
{
    public function test_readable_empty_tables_still_require_runtime_columns_on_selected_connection(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite required');
        $capsule = new \Illuminate\Database\Capsule\Manager(new \Illuminate\Container\Container());
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'selected');
        $db = $capsule->getDatabaseManager();
        $db->setDefaultConnection('selected');
        $connection = $db->connection();
        $columns = [
            'maps' => ['id','name','title','description','parent_map_id','width','height','options','created_at','updated_at'],
            'nodes' => ['id','map_id','label','x','y','device_id','meta','created_at','updated_at'],
            'links' => ['id','map_id','src_node_id','dst_node_id','port_id_a','port_id_b','bandwidth_bps','style','created_at','updated_at'],
            'map_templates' => ['id','name','title','description','width','height','config','icon','category','is_built_in','created_at','updated_at'],
            'map_versions' => ['id','map_id','name','description','config_snapshot','created_by','created_at'],
        ];
        foreach ($columns as $table => $fields) {
            $connection->statement('CREATE TABLE wmng_'.$table.' ('.implode(', ', array_map(fn($field) => '"'.$field.'" TEXT', $fields)).')');
        }
        $connection->statement('CREATE TABLE plugins (plugin_name TEXT, version INTEGER, plugin_active INTEGER)');
        $db->table('plugins')->insert(['plugin_name' => 'WeathermapNG', 'version' => 2, 'plugin_active' => 1]);
        $app = new class($db) {
            public function __construct(private $db) {}
            public function getLoadedProviders() { return ['LibreNMS\\Plugins\\WeathermapNG\\WeathermapNGProvider' => true]; }
            public function make($name) {
                return match ($name) {
                    'db' => $this->db,
                    'router' => new class {
                        public function getRoutes() { return $this; }
                        public function getByName($name) { return new \stdClass(); }
                    },
                    'config' => new class { public function get($name) { return null; } },
                };
            }
            public function routesAreCached() { return false; }
            public function configurationIsCached() { return false; }
        };
        $dir = sys_get_temp_dir().'/wmng-schema-report-'.bin2hex(random_bytes(6));
        mkdir($dir.'/output/maps', 0700, true);
        mkdir($dir.'/output/cache', 0700);
        foreach (['composer.json','composer.plugins.json'] as $file) {
            file_put_contents($dir.'/'.$file, '{"require":{"librenms/weathermapng":"*"}}');
        }
        file_put_contents($dir.'/VERSION', 'test');
        try {
            $this->assertTrue(InstallationReport::collect($app, $dir, $dir)['ok'], 'complete empty schema is healthy');
            foreach (['maps' => 'title', 'nodes' => 'meta', 'links' => 'src_node_id',
                'map_templates' => 'config', 'map_versions' => 'config_snapshot'] as $table => $column) {
                $connection->statement('ALTER TABLE wmng_'.$table.' DROP COLUMN "'.$column.'"');
                $report = InstallationReport::collect($app, $dir, $dir);
                $this->assertFalse($report['ok'], 'missing runtime column in '.$table.' fails installation health');
                $this->assertSame(0, $report['counts'][$table], 'readable count remains genuinely empty');
                $this->assertTrue($report['checks']['table_'.$table]['ok'], 'readable counts are separate from schema usability');
                $this->assertFalse($report['checks']['schema_'.$table]['ok']);
                $this->assertStringNotContainsString('SQLSTATE', json_encode($report));
                $connection->statement('ALTER TABLE wmng_'.$table.' ADD COLUMN "'.$column.'" TEXT');
            }
            $connection->statement('DROP TABLE wmng_maps');
            $connection->statement('CREATE TABLE wmng_maps (id INTEGER PRIMARY KEY)');
            $report = InstallationReport::collect($app, $dir, $dir);
            $this->assertSame(0, $report['counts']['maps']);
            $this->assertFalse($report['ok'], 'id-only table is not usable');
            $this->assertFalse($report['checks']['schema_maps']['ok']);
            $connection->statement('DROP TABLE wmng_maps');
            $report = InstallationReport::collect($app, $dir, $dir);
            $this->assertNull($report['counts']['maps'], 'unavailable table count remains unknown');
            $this->assertFalse($report['checks']['schema_maps']['ok']);
        } finally {
            $connection->disconnect();
            foreach (['composer.json','composer.plugins.json','VERSION'] as $file) unlink($dir.'/'.$file);
            rmdir($dir.'/output/maps');
            rmdir($dir.'/output/cache');
            rmdir($dir.'/output');
            rmdir($dir);
        }
    }

    public function test_missing_runtime_is_unknown_and_report_does_not_repair_or_disclose_config(): void
    {
        $dir = sys_get_temp_dir() . '/wmng-report-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/composer.json', json_encode(['require' => ['librenms/weathermapng' => '*'], 'secret' => 'do-not-disclose']));
        file_put_contents($dir . '/VERSION', '1.14.1');
        try {
            $report = InstallationReport::collect(null, $dir, $dir);
            $this->assertFalse($report['ok']);
            $this->assertTrue($report['checks']['composer_declaration']['ok']);
            $this->assertFalse($report['checks']['persistent_registry']['ok']);
            $this->assertNull($report['counts']['maps']);
            $this->assertFalse($report['checks']['database']['ok']);
            $this->assertStringContainsString('connectivity', $report['checks']['table_maps']['recovery']);
            $this->assertStringContainsString(escapeshellarg($dir), $report['checks']['persistent_registry']['recovery']);
            $this->assertNull($report['authentication']['embed_token_configured']);
            $this->assertDirectoryDoesNotExist($dir . '/output');
            $this->assertStringNotContainsString('do-not-disclose', json_encode($report));
        } finally {
            unlink($dir . '/composer.json');
            unlink($dir . '/VERSION');
            rmdir($dir);
        }
    }
    public function test_cli_missing_bootstrap_returns_safe_json_and_nonzero(): void
    {
        $process = new \Symfony\Component\Process\Process(
            [PHP_BINARY, dirname(__DIR__) . '/verify-deployment.php', '--json'],
            null,
            ['LIBRENMS_PATH' => sys_get_temp_dir() . '/wmng-missing-' . bin2hex(random_bytes(6))]
        );
        $this->assertSame(1, $process->run());
        $this->assertSame('', $process->getErrorOutput());
        $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($report['ok']);
        $this->assertNull($report['counts']['maps']);
    }

    public function test_cli_discards_bootstrap_output_and_exception_details(): void
    {
        $dir = sys_get_temp_dir() . '/wmng-bootstrap-' . bin2hex(random_bytes(6));
        mkdir($dir . '/vendor', 0755, true);
        mkdir($dir . '/bootstrap');
        file_put_contents($dir . '/vendor/autoload.php', '<?php echo "do-not-disclose-output";');
        file_put_contents($dir . '/bootstrap/app.php', '<?php throw new RuntimeException("do-not-disclose-exception");');
        try {
            $process = new \Symfony\Component\Process\Process(
                [PHP_BINARY, dirname(__DIR__) . '/verify-deployment.php', '--json'], null, ['LIBRENMS_PATH' => $dir]
            );
            $this->assertSame(1, $process->run());
            $this->assertStringNotContainsString('do-not-disclose', $process->getOutput() . $process->getErrorOutput());
            $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertFalse($report['checks']['bootstrap']['ok']);
        } finally {
            unlink($dir . '/vendor/autoload.php');
            unlink($dir . '/bootstrap/app.php');
            rmdir($dir . '/vendor');
            rmdir($dir . '/bootstrap');
            rmdir($dir);
        }
    }
}
