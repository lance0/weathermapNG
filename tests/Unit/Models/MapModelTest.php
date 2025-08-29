<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Unit\Models;

use LibreNMS\Plugins\WeathermapNG\Tests\TestCase;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;

class MapModelTest extends TestCase
{
    /** @test */
    public function it_has_fillable_attributes()
    {
        $map = new Map();

        $this->assertContains('name', $map->getFillable());
        $this->assertContains('title', $map->getFillable());
        $this->assertContains('options', $map->getFillable());
    }

    /** @test */
    public function it_has_correct_table_name()
    {
        $map = new Map();
        $this->assertEquals('wmng_maps', $map->getTable());
    }

    /** @test */
    public function it_casts_options_as_array()
    {
        $map = new Map();
        $casts = $map->getCasts();

        $this->assertArrayHasKey('options', $casts);
        $this->assertEquals('array', $casts['options']);
    }

    /** @test */
    public function it_has_nodes_relationship()
    {
        $map = new Map();
        $relation = $map->nodes();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
        $this->assertEquals('map_id', $relation->getForeignKeyName());
    }

    /** @test */
    public function it_has_links_relationship()
    {
        $map = new Map();
        $relation = $map->links();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
        $this->assertEquals('map_id', $relation->getForeignKeyName());
    }

    /** @test */
    public function it_returns_correct_width_attribute()
    {
        $map = Map::create([
            'name' => 'width-test',
            'options' => ['width' => 1200, 'height' => 800]
        ]);

        $this->assertEquals(1200, $map->width);
    }

    /** @test */
    public function it_returns_correct_height_attribute()
    {
        $map = Map::create([
            'name' => 'height-test',
            'options' => ['width' => 1200, 'height' => 800]
        ]);

        $this->assertEquals(800, $map->height);
    }

    /** @test */
    public function it_returns_correct_background_attribute()
    {
        $map = Map::create([
            'name' => 'bg-test',
            'options' => ['background' => '#ff0000']
        ]);

        $this->assertEquals('#ff0000', $map->background);
    }

    /** @test */
    public function it_returns_default_values_when_options_missing()
    {
        $map = Map::create(['name' => 'defaults-test']);

        $this->assertEquals(800, $map->width);  // Default width
        $this->assertEquals(600, $map->height); // Default height
        $this->assertEquals('#ffffff', $map->background); // Default background
    }

    /** @test */
    public function it_generates_correct_json_model()
    {
        $map = Map::create([
            'name' => 'json-model-test',
            'title' => 'JSON Model Test',
            'options' => ['width' => 1000, 'height' => 750, 'background' => '#f0f0f0']
        ]);

        $jsonModel = $map->toJsonModel();

        $expectedStructure = [
            'id',
            'name',
            'title',
            'width',
            'height',
            'background',
            'options',
            'nodes',
            'links',
            'metadata'
        ];

        foreach ($expectedStructure as $key) {
            $this->assertArrayHasKey($key, $jsonModel, "Missing key: {$key}");
        }

        $this->assertEquals('json-model-test', $jsonModel['name']);
        $this->assertEquals('JSON Model Test', $jsonModel['title']);
        $this->assertEquals(1000, $jsonModel['width']);
        $this->assertEquals(750, $jsonModel['height']);
        $this->assertEquals('#f0f0f0', $jsonModel['background']);
        $this->assertIsArray($jsonModel['nodes']);
        $this->assertIsArray($jsonModel['links']);
        $this->assertIsArray($jsonModel['metadata']);
    }

    /** @test */
    public function it_includes_nodes_in_json_model()
    {
        $map = Map::create(['name' => 'nodes-test']);
        $node = Node::create([
            'map_id' => $map->id,
            'label' => 'Test Node',
            'x' => 100,
            'y' => 200,
            'device_id' => 1
        ]);

        $jsonModel = $map->toJsonModel();

        $this->assertCount(1, $jsonModel['nodes']);
        $this->assertEquals('Test Node', $jsonModel['nodes'][0]['label']);
        $this->assertEquals(100, $jsonModel['nodes'][0]['x']);
        $this->assertEquals(200, $jsonModel['nodes'][0]['y']);
    }

    /** @test */
    public function it_includes_links_in_json_model()
    {
        $map = Map::create(['name' => 'links-test']);
        $node1 = Node::create(['map_id' => $map->id, 'label' => 'Node 1', 'x' => 0, 'y' => 0]);
        $node2 = Node::create(['map_id' => $map->id, 'label' => 'Node 2', 'x' => 100, 'y' => 100]);

        $link = Link::create([
            'map_id' => $map->id,
            'src_node_id' => $node1->id,
            'dst_node_id' => $node2->id,
            'bandwidth_bps' => 1000000000
        ]);

        $jsonModel = $map->toJsonModel();

        $this->assertCount(1, $jsonModel['links']);
        $this->assertEquals($node1->id, $jsonModel['links'][0]['src']);
        $this->assertEquals($node2->id, $jsonModel['links'][0]['dst']);
        $this->assertEquals(1000000000, $jsonModel['links'][0]['bandwidth_bps']);
    }

    /** @test */
    public function it_updates_metadata_correctly()
    {
        $map = Map::create(['name' => 'metadata-test']);
        Node::create(['map_id' => $map->id, 'label' => 'Node 1', 'x' => 0, 'y' => 0]);
        Node::create(['map_id' => $map->id, 'label' => 'Node 2', 'x' => 100, 'y' => 100]);
        Link::create([
            'map_id' => $map->id,
            'src_node_id' => 1,
            'dst_node_id' => 2,
            'bandwidth_bps' => 1000000000
        ]);

        $jsonModel = $map->toJsonModel();

        $this->assertEquals(2, $jsonModel['metadata']['total_nodes']);
        $this->assertEquals(1, $jsonModel['metadata']['total_links']);
        $this->assertArrayHasKey('last_updated', $jsonModel['metadata']);
    }

    /** @test */
    public function it_handles_empty_maps()
    {
        $map = Map::create(['name' => 'empty-test']);

        $jsonModel = $map->toJsonModel();

        $this->assertEquals('empty-test', $jsonModel['name']);
        $this->assertEmpty($jsonModel['nodes']);
        $this->assertEmpty($jsonModel['links']);
        $this->assertEquals(0, $jsonModel['metadata']['total_nodes']);
        $this->assertEquals(0, $jsonModel['metadata']['total_links']);
    }

    /** @test */
    public function it_preserves_options_structure()
    {
        $options = [
            'width' => 1200,
            'height' => 900,
            'background' => '#3366cc',
            'grid_size' => 25,
            'theme' => 'corporate'
        ];

        $map = Map::create([
            'name' => 'options-test',
            'options' => $options
        ]);

        $jsonModel = $map->toJsonModel();

        $this->assertEquals($options, $jsonModel['options']);
        $this->assertEquals(1200, $jsonModel['width']);
        $this->assertEquals(900, $jsonModel['height']);
        $this->assertEquals('#3366cc', $jsonModel['background']);
    }
}