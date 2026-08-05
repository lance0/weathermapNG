<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\Facade;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use LibreNMS\Plugins\WeathermapNG\Services\MapService;
use LibreNMS\Plugins\WeathermapNG\Services\RrdDataService;
use LibreNMS\Plugins\WeathermapNG\RRD\RRDTool;

/**
 * Integration tests for MapService::getDataIntegrityIssues().
 *
 * Boots an in-memory SQLite database (without foreign-key constraints, so
 * orphan and broken rows can be inserted — exactly the drift the integrity
 * scan is meant to surface) and points RrdDataService at a temp RRD dir.
 */
class DiagnosticsIntegrationTest extends TestCase
{
    private ?string $rrdDir = null;
    private string $hostname = 'switch01';

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('Illuminate\Database\DatabaseManager')
            || !class_exists('Illuminate\Database\Eloquent\Model')
            || !class_exists('Illuminate\Database\Connectors\SQLiteConnector')) {
            $this->markTestSkipped('Laravel database components not available');
            return;
        }

        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension not available');
            return;
        }

        // Temp RRD directory; seed a per-device subdir lazily in tests.
        $this->rrdDir = sys_get_temp_dir() . '/wmng_integrity_' . bin2hex(random_bytes(4));
        mkdir($this->rrdDir . '/' . $this->hostname, 0777, true);

        $this->bootDatabase();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if ($this->rrdDir && is_dir($this->rrdDir)) {
            $this->removeDir($this->rrdDir);
            $this->rrdDir = null;
        }
        parent::tearDown();
    }

    /**
     * Bind a DatabaseManager into the facade application container (set by
     * tests/bootstrap.php) so the DB, Schema and Eloquent facades all resolve
     * against the in-memory connection.
     */
    private function bootDatabase(): void
    {
        $app = Facade::getFacadeApplication();

        // The DatabaseManager reads connection config via the container's
        // 'config' binding using dot notation (Laravel Config\Repository is not
        // installed here), so bind a slim dot-notation adapter over a config array.
        $app->instance('config', new class([
            'database' => [
                'default' => 'sqlite',
                'fetch' => \PDO::FETCH_OBJ,
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                        'prefix' => '',
                        'foreign_key_constraints' => false,
                    ],
                ],
            ],
        ]) implements \ArrayAccess {
            private array $items;

            public function __construct(array $items)
            {
                $this->items = $items;
            }

            public function offsetExists(mixed $offset): bool
            {
                return \Illuminate\Support\Arr::has($this->items, $offset);
            }

            public function offsetGet(mixed $offset): mixed
            {
                return \Illuminate\Support\Arr::get($this->items, $offset);
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
                if ($offset === null) {
                    $this->items[] = $value;
                } else {
                    \Illuminate\Support\Arr::set($this->items, $offset, $value);
                }
            }

            public function offsetUnset(mixed $offset): void
            {
                \Illuminate\Support\Arr::forget($this->items, $offset);
            }
        });

        $app->singleton('db', function ($app) {
            return new DatabaseManager($app, new ConnectionFactory($app));
        });

        // Drop any previously-resolved facade instance so DB:: and Schema::
        // re-resolve against the freshly bound manager (PHPUnit reuses the
        // facade application container across tests).
        \Illuminate\Support\Facades\DB::clearResolvedInstance('db');
        \Illuminate\Support\Facades\Schema::clearResolvedInstance('db');

        Model::setConnectionResolver($app['db']);
        Model::unsetEventDispatcher();
    }

    private function createSchema(): void
    {
        $schema = Facade::getFacadeApplication()['db']->connection()->getSchemaBuilder();

        // LibreNMS core tables used by the integrity scan.
        $schema->create('devices', function (Blueprint $t) {
            $t->increments('device_id');
            $t->string('hostname');
            $t->string('sysName')->nullable();
            $t->string('rrd_path')->nullable();
        });

        $schema->create('ports', function (Blueprint $t) {
            $t->increments('port_id');
            $t->integer('device_id');
            $t->integer('ifIndex')->nullable();
            $t->string('ifName')->nullable();
        });

        // wmng tables (no FKs so orphan rows can be inserted).
        $schema->create('wmng_maps', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->unique();
            $t->string('title')->nullable();
            $t->string('description')->nullable();
            $t->integer('width')->default(800);
            $t->integer('height')->default(600);
            $t->json('options')->nullable();
            $t->timestamps();
        });

        $schema->create('wmng_nodes', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('map_id');
            $t->string('label');
            $t->float('x');
            $t->float('y');
            $t->integer('device_id')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        $schema->create('wmng_links', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('map_id');
            $t->integer('src_node_id');
            $t->integer('dst_node_id');
            $t->integer('port_id_a')->nullable();
            $t->integer('port_id_b')->nullable();
            $t->bigInteger('bandwidth_bps')->nullable();
            $t->json('style')->nullable();
            $t->timestamps();
        });
    }

    private function seedDevice(int $deviceId, string $hostname): void
    {
        \Illuminate\Support\Facades\DB::table('devices')->insert([
            'device_id' => $deviceId,
            'hostname' => $hostname,
        ]);
    }

    private function seedPort(int $portId, int $deviceId, string $ifName): void
    {
        \Illuminate\Support\Facades\DB::table('ports')->insert([
            'port_id' => $portId,
            'device_id' => $deviceId,
            'ifName' => $ifName,
        ]);
    }

    private function seedMap(string $name, ?string $title = null): int
    {
        $id = \Illuminate\Support\Facades\DB::table('wmng_maps')->insertGetId([
            'name' => $name,
            'title' => $title,
            'options' => json_encode(['width' => 800, 'height' => 600]),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $id;
    }

    private function seedNode(int $mapId, ?int $deviceId = null): int
    {
        return \Illuminate\Support\Facades\DB::table('wmng_nodes')->insertGetId([
            'map_id' => $mapId,
            'label' => 'N' . $deviceId,
            'x' => 0,
            'y' => 0,
            'device_id' => $deviceId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedLink(int $mapId, int $srcNode, int $dstNode, ?int $portA = null, ?int $portB = null): int
    {
        return \Illuminate\Support\Facades\DB::table('wmng_links')->insertGetId([
            'map_id' => $mapId,
            'src_node_id' => $srcNode,
            'dst_node_id' => $dstNode,
            'port_id_a' => $portA,
            'port_id_b' => $portB,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create an actual RRD file at the standard LibreNMS path so the resolver
     * reports it present. Returns the canonical path for reference.
     */
    private function createRrdFile(int $deviceId, string $ifName): string
    {
        $hostname = \Illuminate\Support\Facades\DB::table('devices')
            ->where('device_id', $deviceId)->value('hostname');
        $sanitized = preg_replace('/[\/\s:]+/', '-', $ifName);
        $file = "{$this->rrdDir}/{$hostname}/port-{$sanitized}.rrd";
        file_put_contents($file, '');
        return $file;
    }

    private function rrdService(): RrdDataService
    {
        return new RrdDataService(new RRDTool(), $this->rrdDir);
    }

    private function integrityReport(int $limit = 20): array
    {
        return (new MapService())->getDataIntegrityIssues($this->rrdService(), $limit);
    }

    public function test_detects_broken_port_missing_rrd_and_orphans(): void
    {
        // Two candidate endpoints.
        $this->seedDevice(1, $this->hostname);
        $this->seedPort(10, 1, 'GigabitEthernet0/1');
        $this->seedPort(11, 1, 'GigabitEthernet0/2');
        $this->createRrdFile(1, 'GigabitEthernet0/1'); // present for port 10

        $mapId = $this->seedMap('map-a', 'Map A');

        $n1 = $this->seedNode($mapId, 1); // valid device
        $n2 = $this->seedNode($mapId, 999); // orphan: device_id unknown

        // valid link: ports 10/11 both exist; port 11 has no RRD file -> missing_rrd
        $this->seedLink($mapId, $n1, $n2, 10, 11);

        // link with broken port (port 99 does not exist)
        $this->seedLink($mapId, $n1, $n2, 99, null);

        // orphan link: references a node id that does not exist
        $this->seedLink($mapId, 424242, $n1);

        // hanging rows (bad map_id) -> dangling counters
        $this->seedNode(999999);
        \Illuminate\Support\Facades\DB::table('wmng_links')->insert([
            'map_id' => 999999,
            'src_node_id' => 1,
            'dst_node_id' => 2,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $report = $this->integrityReport();

        $this->assertCount(1, $report['maps']);
        $map = $report['maps'][0];

        $this->assertSame('Map A', $map['name']);
        $this->assertSame(2, $map['nodes']);
        $this->assertSame(3, $map['links']);
        // node n2 (device 999) + ... device 999 unknown -> 1 orphan node
        $this->assertSame(1, $map['orphan_nodes']);
        // link 99 broken; link with missing-nodes is an orphan link
        $this->assertSame(1, $map['broken_links']);
        $this->assertSame(1, $map['orphan_links']);
        // port 11 exists but has no RRD file -> 1 missing
        $this->assertSame(1, $map['missing_rrd']);

        $types = array_column($map['findings'], 'type');
        $this->assertContains('broken_port', $types);
        $this->assertContains('orphan_link', $types);
        $this->assertContains('orphan_node', $types);
        $this->assertContains('missing_rrd', $types);

        $this->assertSame(4, $map['total']);
        $this->assertSame(1, $report['summary']['dangling_nodes']);
        $this->assertSame(1, $report['summary']['dangling_links']);
        $this->assertSame(1, $report['summary']['broken_links']);
        $this->assertSame(1, $report['summary']['missing_rrd']);
        $this->assertSame(1, $report['summary']['orphan_nodes']);
        $this->assertSame(1, $report['summary']['orphan_links']);
    }

    public function test_all_clean_map_reports_no_issues(): void
    {
        $this->seedDevice(1, $this->hostname);
        $this->seedPort(10, 1, 'GigabitEthernet0/1');
        $this->seedPort(11, 1, 'GigabitEthernet0/2');
        $this->createRrdFile(1, 'GigabitEthernet0/1');
        $this->createRrdFile(1, 'GigabitEthernet0/2');

        $mapId = $this->seedMap('clean-map', 'Clean Map');
        $n1 = $this->seedNode($mapId, 1);
        $n2 = $this->seedNode($mapId, 1);
        $this->seedLink($mapId, $n1, $n2, 10, 11);

        $report = $this->integrityReport();
        $this->assertCount(1, $report['maps']);
        $map = $report['maps'][0];

        $this->assertSame('Clean Map', $map['name']);
        $this->assertSame(2, $map['nodes']);
        $this->assertSame(1, $map['links']);
        $this->assertSame(0, $map['broken_links']);
        $this->assertSame(0, $map['missing_rrd']);
        $this->assertSame(0, $map['orphan_nodes']);
        $this->assertSame(0, $map['orphan_links']);
        $this->assertSame(0, $map['total']);
        $this->assertSame([], $map['findings']);

        $this->assertSame(0, $report['summary']['dangling_nodes']);
        $this->assertSame(0, $report['summary']['dangling_links']);
        $this->assertSame(0, $report['summary']['broken_links']);
    }

    public function test_findings_are_capped_but_counts_are_accurate(): void
    {
        $this->seedDevice(1, $this->hostname);

        $mapId = $this->seedMap('cap-map', 'Cap Map');
        $n1 = $this->seedNode($mapId, 1);

        // Three links, all pointing at the same missing port -> 3 broken.
        for ($i = 0; $i < 3; $i++) {
            $this->seedLink($mapId, $n1, $n1, 555, null);
        }

        $report = $this->integrityReport(1);
        $map = $report['maps'][0];

        // Full counts regardless of the findings cap.
        $this->assertSame(3, $map['broken_links']);
        $this->assertSame(3, $map['total']);
        // Only the cap is surfaced as specific findings; the sample references
        // the broken port.
        $this->assertCount(1, $map['findings']);
        $this->assertSame('broken_port', $map['findings'][0]['type']);
        $this->assertStringContainsString('555', $map['findings'][0]['message']);
    }

    public function test_map_with_no_links_but_valid_rows_is_clean(): void
    {
        $this->seedDevice(1, $this->hostname);
        $mapId = $this->seedMap('empty-map');
        $this->seedNode($mapId, 1);

        $report = $this->integrityReport();
        $map = $report['maps'][0];

        $this->assertSame(1, $map['nodes']);
        $this->assertSame(0, $map['links']);
        $this->assertSame(0, $map['total']);
        $this->assertSame(0, $report['summary']['dangling_nodes']);
        $this->assertSame(0, $report['summary']['dangling_links']);
    }

    public function test_empty_database_reports_no_maps(): void
    {
        $report = $this->integrityReport();
        $this->assertSame([], $report['maps']);
        $this->assertSame(0, $report['summary']['maps']);
        $this->assertSame(0, $report['summary']['dangling_nodes']);
    }

    private function removeDir(string $dir): void
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}