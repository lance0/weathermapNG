<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_map()
    {
        $map = Map::create([
            'name' => 'test_map',
            'title' => 'Test Map',
            'options' => ['width' => 800, 'height' => 600]
        ]);

        $this->assertDatabaseHas('wmng_maps', [
            'name' => 'test_map',
            'title' => 'Test Map'
        ]);

        $this->assertEquals(800, $map->width);
        $this->assertEquals(600, $map->height);
    }

    /** @test */
    public function it_can_add_nodes_to_map()
    {
        $map = Map::factory()->create();

        $node = Node::create([
            'map_id' => $map->id,
            'label' => 'Test Node',
            'x' => 100,
            'y' => 100,
            'device_id' => 1
        ]);

        $this->assertEquals(1, $map->nodes()->count());
        $this->assertEquals('Test Node', $node->label);
    }

    /** @test */
    public function it_can_create_links_between_nodes()
    {
        $map = Map::factory()->create();
        $node1 = Node::factory()->create(['map_id' => $map->id]);
        $node2 = Node::factory()->create(['map_id' => $map->id]);

        $link = Link::create([
            'map_id' => $map->id,
            'src_node_id' => $node1->id,
            'dst_node_id' => $node2->id,
            'bandwidth_bps' => 1000000000
        ]);

        $this->assertEquals(1, $map->links()->count());
        $this->assertEquals(1000000000, $link->bandwidth_bps);
    }

    /** @test */
    public function it_returns_correct_json_model()
    {
        $map = Map::factory()->create([
            'name' => 'json_test',
            'title' => 'JSON Test Map'
        ]);

        $jsonModel = $map->toJsonModel();

        $this->assertEquals('json_test', $jsonModel['name']);
        $this->assertEquals('JSON Test Map', $jsonModel['title']);
        $this->assertArrayHasKey('nodes', $jsonModel);
        $this->assertArrayHasKey('links', $jsonModel);
    }

    /** @test */
    public function it_enforces_unique_map_names()
    {
        Map::create(['name' => 'duplicate', 'title' => 'First Map']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Map::create(['name' => 'duplicate', 'title' => 'Second Map']);
    }

    /** @test */
    public function it_cascades_deletes()
    {
        $map = Map::factory()->create();
        $node = Node::factory()->create(['map_id' => $map->id]);
        $link = Link::factory()->create(['map_id' => $map->id]);

        $map->delete();

        $this->assertDatabaseMissing('wmng_maps', ['id' => $map->id]);
        $this->assertDatabaseMissing('wmng_nodes', ['id' => $node->id]);
        $this->assertDatabaseMissing('wmng_links', ['id' => $link->id]);
    }
}