<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;

class NestedMapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension not available');
            return;
        }

        // Fresh, isolated in-memory schema per test. Mirrors AutoDiscoveryTest:
        // build the Capsule on the bootstrap container so DB facade, Eloquent
        // models, and capsule share the same connection.
        $app = Facade::getFacadeApplication();
        $capsule = new Capsule($app);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $app->instance('db', $capsule->getDatabaseManager());
        Facade::clearResolvedInstance('db');

        $this->createSchema($capsule);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstance('db');
        parent::tearDown();
    }

    private function createSchema($capsule): void
    {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->dropIfExists('wmng_links');
        $schema->dropIfExists('wmng_nodes');
        $schema->dropIfExists('wmng_maps');

        $schema->create('wmng_maps', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('title')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('parent_map_id')->nullable();
            $t->index('parent_map_id');
            $t->text('options')->nullable();
            $t->timestamps();
        });

        $schema->create('wmng_nodes', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('map_id');
            $t->string('label');
            $t->float('x');
            $t->float('y');
            $t->unsignedInteger('device_id')->nullable();
            $t->text('meta')->nullable();
            $t->timestamps();
        });

        $schema->create('wmng_links', function (Blueprint $t) {
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
    }

    private function makeRoot(string $name = 'root'): Map
    {
        return Map::create(['name' => $name, 'title' => 'Root', 'options' => []]);
    }

    private function makeChild(Map $parent, string $name = 'child'): Map
    {
        return Map::create([
            'name' => $name,
            'title' => 'Child',
            'parent_map_id' => $parent->id,
            'options' => [],
        ]);
    }

    public function test_parent_map_relation_resolves(): void
    {
        $root = $this->makeRoot('site');
        $child = $this->makeChild($root, 'rack-1');

        $this->assertSame($root->id, $child->parentMap->id);
        $this->assertTrue($root->childMaps->contains($child));
    }

    public function test_breadcrumb_from_root_length_one(): void
    {
        $root = $this->makeRoot('campus');

        $crumb = $root->breadcrumb();

        $this->assertCount(1, $crumb);
        $this->assertSame($root->id, $crumb[0]['id']);
        $this->assertSame('campus', $crumb[0]['name']);
    }

    public function test_breadcrumb_walks_to_root_in_order(): void
    {
        $root = $this->makeRoot('campus');
        $bldg = $this->makeChild($root, 'building-a');
        $rack = $this->makeChild($bldg, 'rack-3');

        $crumb = $rack->breadcrumb();

        $this->assertCount(3, $crumb);
        $this->assertSame('campus', $crumb[0]['name']);
        $this->assertSame('building-a', $crumb[1]['name']);
        $this->assertSame('rack-3', $crumb[2]['name']);
    }

    public function test_breadcrumb_does_not_infinitely_loop_on_cycles(): void
    {
        $a = $this->makeRoot('a');
        $b = $this->makeChild($a, 'b');
        // Corrupt into a cycle: make a's parent point at its own child.
        $a->parent_map_id = $b->id;
        $a->save();

        $crumb = $a->breadcrumb();

        // Bounded walk — no infinite loop, no memory blowout.
        $this->assertLessThanOrEqual(21, count($crumb));
    }

    public function test_to_json_model_includes_sub_map_id_and_breadcrumb(): void
    {
        $root = $this->makeRoot('campus');
        $rack = $this->makeChild($root, 'rack-3');

        Node::create([
            'map_id' => $root->id, 'label' => 'Building A', 'x' => 100, 'y' => 100,
            'meta' => ['sub_map_id' => $rack->id],
        ]);
        Node::create([
            'map_id' => $root->id, 'label' => 'Plain device', 'x' => 200, 'y' => 200,
        ]);

        $root = Map::with(['nodes', 'links'])->find($root->id);
        $json = $root->toJsonModel();

        $this->assertNull($json['parent_map_id']);
        $this->assertCount(1, $json['breadcrumb']);
        $this->assertSame($root->id, $json['breadcrumb'][0]['id']);

        // Drill-down from a child's perspective: the child's breadcrumb
        // walks root → child.
        $childJson = Map::with(['nodes', 'links'])->find($rack->id)->toJsonModel();
        $this->assertCount(2, $childJson['breadcrumb']);
        $this->assertSame($root->id, $childJson['breadcrumb'][0]['id']);
        $this->assertSame($rack->id, $childJson['breadcrumb'][1]['id']);
        $this->assertSame($root->id, $childJson['parent_map_id']);

        $n1 = collect($json['nodes'])->firstWhere('label', 'Building A');
        $n2 = collect($json['nodes'])->firstWhere('label', 'Plain device');
        $this->assertSame($rack->id, $n1['sub_map_id']);
        $this->assertNull($n2['sub_map_id']);
    }

    public function test_node_sub_map_accessor_normalizes(): void
    {
        $root = $this->makeRoot('root');
        $n = new Node(['map_id' => $root->id, 'label' => 'x', 'x' => 0, 'y' => 0]);

        $n->setSubMapIdAttribute(42);
        $this->assertSame(42, $n->getSubMapIdAttribute());
        $this->assertSame(42, $n->meta['sub_map_id']);

        $n->setSubMapIdAttribute(null);
        $this->assertNull($n->getSubMapIdAttribute());
        $this->assertArrayNotHasKey('sub_map_id', $n->meta);
    }
}
