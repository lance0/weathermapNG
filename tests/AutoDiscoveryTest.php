<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\Services\AutoDiscoveryService;

/**
 * End-to-end tests for the LLDP/CDP auto-discovery path. A real sqlite
 * in-memory database is used so DB::table(...) (LibreNMS devices/ports/links)
 * and the Eloquent wmng_* models resolve against the same connection.
 */
class AutoDiscoveryTest extends TestCase
{
    private AutoDiscoveryService $service;

    private Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension not available');
            return;
        }

        // Fresh, isolated in-memory schema per test. Build the Capsule on the
        // bootstrap container so the DB facade, Eloquent models, and the
        // capsule all resolve the same "db" connection manager.
        $app = Facade::getFacadeApplication();
        $this->capsule = new Capsule($app);
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->capsule->bootEloquent();

        // DatabaseManager does not self-register; bind it as "db" on the shared
        // container so the DB facade resolves it (Eloquent already resolves the
        // same manager via bootEloquent's connection resolver).
        $app->instance('db', $this->capsule->getDatabaseManager());
        Facade::clearResolvedInstance('db');

        $this->createSchema($this->capsule);

        $this->service = new AutoDiscoveryService();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstance('db');
        parent::tearDown();
    }

    private function createSchema(Capsule $capsule): void
    {
        $schema = $capsule->getConnection()->getSchemaBuilder();

        $schema->create('wmng_maps', function ($t) {
            $t->increments('id');
            $t->string('name');
            $t->string('title')->nullable();
            $t->text('options')->nullable();
            $t->timestamps();
        });

        $schema->create('wmng_nodes', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('map_id');
            $t->string('label');
            $t->float('x');
            $t->float('y');
            $t->unsignedInteger('device_id')->nullable();
            $t->text('meta')->nullable();
            $t->timestamps();
        });

        $schema->create('wmng_links', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('map_id');
            $t->unsignedInteger('src_node_id');
            $t->unsignedInteger('dst_node_id');
            $t->unsignedInteger('port_id_a')->nullable();
            $t->unsignedInteger('port_id_b')->nullable();
            $t->bigInteger('bandwidth_bps')->nullable();
            $t->text('style')->nullable();
            $t->timestamps();
        });

        // LibreNMS core tables (non-wmng_).
        $schema->create('devices', function ($t) {
            $t->increments('device_id');
            $t->string('hostname');
            $t->string('os')->default('');
            $t->boolean('disabled')->default(0);
            $t->boolean('ignore')->default(0);
            $t->boolean('status')->default(1);
        });

        $schema->create('ports', function ($t) {
            $t->increments('port_id');
            $t->unsignedInteger('device_id');
            $t->unsignedInteger('ifIndex');
            $t->string('ifDescr')->nullable();
            $t->string('ifOperStatus')->default('up');
            $t->string('ifAdminStatus')->default('up');
        });

        // LibreNMS topology (LLDP/XDP/CDP) links table.
        $schema->create('links', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('local_device_id');
            $t->unsignedInteger('local_port_id');
            $t->string('protocol')->default('lldp');
            $t->string('remote_hostname')->nullable();
            $t->unsignedInteger('remote_device_id');
            $t->unsignedInteger('remote_port_id');
        });
    }

    private function createMap(int $id): Map
    {
        $map = Map::find($id);
        return $map;
    }

    private function seedDevice(int $id, string $hostname, string $os = 'linux'): void
    {
        $this->capsule->getConnection()->table('devices')->insert([
            'device_id' => $id,
            'hostname' => $hostname,
            'os' => $os,
            'disabled' => 0,
            'ignore' => 0,
        ]);
    }

    private function seedPort(int $deviceId, int $portId, int $ifIndex): void
    {
        $this->capsule->getConnection()->table('ports')->insert([
            'port_id' => $portId,
            'device_id' => $deviceId,
            'ifIndex' => $ifIndex,
            'ifDescr' => "eth{$ifIndex}",
            'ifOperStatus' => 'up',
            'ifAdminStatus' => 'up',
        ]);
    }

    private function seedTopologyLink(
        int $localDevice,
        int $localPort,
        int $remoteDevice,
        int $remotePort,
        string $protocol = 'lldp'
    ): void {
        $this->capsule->getConnection()->table('links')->insert([
            'local_device_id' => $localDevice,
            'local_port_id' => $localPort,
            'protocol' => $protocol,
            'remote_device_id' => $remoteDevice,
            'remote_port_id' => $remotePort,
        ]);
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    public function test_validate_normalizes_min_degree_and_os_list(): void
    {
        $params = $this->service->validateDiscoveryParams([
            'min_degree' => '-3',
            'os' => ' linux , ios , junos ',
        ]);

        $this->assertEquals(0, $params['minDegree']);
        $this->assertEquals(['linux', 'ios', 'junos'], $params['osFilter']);
    }

    public function test_validate_clamps_negative_min_degree_to_zero(): void
    {
        $params = $this->service->validateDiscoveryParams(['min_degree' => '-5']);
        $this->assertEquals(0, $params['minDegree']);
    }

    // ------------------------------------------------------------------
    // Discovery
    // ------------------------------------------------------------------

    public function test_discover_seeds_nodes_and_links_from_topology(): void
    {
        Map::forceCreate(['id' => 1, 'name' => 'core', 'options' => []]);

        $this->seedDevice(10, 'core-a', 'ios');
        $this->seedDevice(20, 'core-b', 'ios');
        $this->seedDevice(30, 'edge', 'linux');

        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);
        $this->seedPort(30, 300, 1);

        // Bidirectional LLDP entries for the (10,20) pair — must collapse to one link.
        $this->seedTopologyLink(10, 100, 20, 200, 'lldp');
        $this->seedTopologyLink(20, 200, 10, 100, 'lldp');
        // CDP link from edge to core-a.
        $this->seedTopologyLink(30, 300, 10, 100, 'cdp');

        $summary = $this->service->discoverAndSeedMap(Map::find(1), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(3, $summary['nodes_added']);
        // 3 candidate devices paired by 3 topology rows, but (10,20) appears
        // twice (both directions), so 2 distinct device pairs yield 2 links.
        $this->assertEquals(2, $summary['links_added']);

        $this->assertSame(3, Node::where('map_id', 1)->count());

        $links = Link::where('map_id', 1)->get();
        $this->assertCount(2, $links);

        // Link ports must carry the topology port_ids.
        $pairByDevice = [];
        foreach ($links as $link) {
            $a = Node::find($link->src_node_id)->device_id;
            $b = Node::find($link->dst_node_id)->device_id;
            $pairByDevice[($a < $b ? $a : $b) . '-' . ($a < $b ? $b : $a)] = $link;
        }

        $this->assertArrayHasKey('10-20', $pairByDevice);
        $link1020 = $pairByDevice['10-20'];
        $this->assertEquals(100, $link1020->port_id_a);
        $this->assertEquals(200, $link1020->port_id_b);

        $this->assertArrayHasKey('10-30', $pairByDevice);
        // Ordering of src/dst is min/max; ports on the 10-30 link are
        // 300 (edge) and 100 (core-a).
        $link1030 = $pairByDevice['10-30'];
        $this->assertContains($link1030->port_id_a, [100, 300]);
        $this->assertContains($link1030->port_id_b, [100, 300]);
    }

    public function test_discover_deduplicates_by_device_pair(): void
    {
        Map::forceCreate(['id' => 2, 'name' => 'dedup', 'options' => []]);

        $this->seedDevice(10, 'a', 'linux');
        $this->seedDevice(20, 'b', 'linux');
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);

        // Ten duplicate topology rows for the same pair, in both directions.
        for ($i = 0; $i < 5; $i++) {
            $this->seedTopologyLink(10, 100, 20, 200, 'lldp');
            $this->seedTopologyLink(20, 200, 10, 100, 'cdp');
        }

        $summary = $this->service->discoverAndSeedMap(Map::find(2), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(2, $summary['nodes_added']);
        $this->assertEquals(1, $summary['links_added']);
        $this->assertSame(1, Link::where('map_id', 2)->count());
    }

    public function test_discover_skips_end_with_missing_port_but_keeps_link(): void
    {
        Map::forceCreate(['id' => 3, 'name' => 'missingport', 'options' => []]);

        $this->seedDevice(10, 'a', 'linux');
        $this->seedDevice(20, 'b', 'linux');
        // Device 10 has port 100; device 20 has NO matching port (row references
        // port 999 which does not exist).
        $this->seedPort(10, 100, 1);

        $this->seedTopologyLink(20, 999, 10, 100, 'lldp');

        $summary = $this->service->discoverAndSeedMap(Map::find(3), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(2, $summary['nodes_added']);
        $this->assertEquals(1, $summary['links_added']);

        $link = Link::where('map_id', 3)->first();
        $this->assertNotNull($link);
        // The resolvable end (device 10, port 100) is kept.
        $this->assertEquals(100, $link->port_id_a);
        $this->assertNull($link->port_id_b);
    }

    public function test_discover_uses_topology_protocol_filter(): void
    {
        Map::forceCreate(['id' => 4, 'name' => 'proto', 'options' => []]);

        $this->seedDevice(10, 'a', 'linux');
        $this->seedDevice(20, 'b', 'linux');
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);

        // One supported protocol and one that must be ignored (e.g. ospf).
        $this->seedTopologyLink(10, 100, 20, 200, 'lldp');
        $this->seedTopologyLink(20, 200, 10, 100, 'ospf');

        $summary = $this->service->discoverAndSeedMap(Map::find(4), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(1, $summary['links_added']);
    }

    public function test_discover_with_no_candidate_devices_returns_empty_counts(): void
    {
        Map::forceCreate(['id' => 5, 'name' => 'nocand', 'options' => []]);

        // Only disabled/ignored devices exist.
        $this->capsule->getConnection()->table('devices')->insert([
            'device_id' => 50,
            'hostname' => 'disabled-host',
            'os' => 'linux',
            'disabled' => 1,
            'ignore' => 0,
        ]);

        $summary = $this->service->discoverAndSeedMap(Map::find(5), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(['nodes_added' => 0, 'links_added' => 0], $summary);
        $this->assertSame(0, Node::where('map_id', 5)->count());
        $this->assertSame(0, Link::where('map_id', 5)->count());
    }

    public function test_discover_with_empty_topology_returns_zero_links(): void
    {
        Map::forceCreate(['id' => 6, 'name' => 'notopo', 'options' => []]);

        $this->seedDevice(10, 'a', 'linux');
        $this->seedDevice(20, 'b', 'linux');
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);

        // No rows in the LibreNMS links table at all.
        $summary = $this->service->discoverAndSeedMap(Map::find(6), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(2, $summary['nodes_added']);
        $this->assertEquals(0, $summary['links_added']);
        $this->assertSame(2, Node::where('map_id', 6)->count());
        $this->assertSame(0, Link::where('map_id', 6)->count());
    }

    public function test_discover_skips_existing_preexisting_nodes(): void
    {
        $map = Map::forceCreate(['id' => 7, 'name' => 'existing', 'options' => []]);
        Node::create([
            'map_id' => 7,
            'label' => 'a',
            'x' => 100,
            'y' => 100,
            'device_id' => 10,
            'meta' => [],
        ]);

        $this->seedDevice(10, 'a', 'linux');
        $this->seedDevice(20, 'b', 'linux');
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);
        $this->seedTopologyLink(10, 100, 20, 200, 'lldp');

        $summary = $this->service->discoverAndSeedMap($map, [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        // Only device 20 is new; device 10 already has a node.
        $this->assertEquals(1, $summary['nodes_added']);
        $this->assertEquals(1, $summary['links_added']);
    }
}