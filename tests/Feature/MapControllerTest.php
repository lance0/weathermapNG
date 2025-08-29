<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Feature;

use LibreNMS\Plugins\WeathermapNG\Tests\TestCase;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use Illuminate\Foundation\Testing\WithFaker;

class MapControllerTest extends TestCase
{
    use WithFaker;

    /** @test */
    public function it_displays_maps_index_page()
    {
        // Create some test maps
        Map::create(['name' => 'map1', 'title' => 'Map One']);
        Map::create(['name' => 'map2', 'title' => 'Map Two']);

        $response = $this->get('/plugins/weathermapng');

        $response->assertStatus(200);
        $response->assertViewHas('maps');
        $response->assertSee('Map One');
        $response->assertSee('Map Two');
    }

    /** @test */
    public function it_shows_create_map_form()
    {
        $response = $this->get('/plugins/weathermapng/create');

        $response->assertStatus(200);
        $response->assertViewIs('plugins.WeathermapNG.create');
    }

    /** @test */
    public function it_can_create_a_new_map()
    {
        $mapData = [
            'name' => 'new-test-map',
            'title' => 'New Test Map',
            'width' => 1024,
            'height' => 768
        ];

        $response = $this->post('/plugins/weathermapng/maps', $mapData);

        $response->assertRedirect('/plugins/weathermapng');
        $this->assertDatabaseHas('wmng_maps', [
            'name' => 'new-test-map',
            'title' => 'New Test Map'
        ]);

        $map = Map::where('name', 'new-test-map')->first();
        $this->assertEquals(1024, $map->width);
        $this->assertEquals(768, $map->height);
    }

    /** @test */
    public function it_validates_map_creation_data()
    {
        $invalidData = [
            'name' => '', // Required field empty
            'title' => str_repeat('a', 300), // Too long
        ];

        $response = $this->post('/plugins/weathermapng/maps', $invalidData);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['name', 'title']);
        $this->assertDatabaseMissing('wmng_maps', ['name' => '']);
    }

    /** @test */
    public function it_prevents_duplicate_map_names()
    {
        // Create first map
        Map::create(['name' => 'duplicate', 'title' => 'First Map']);

        // Try to create second map with same name
        $response = $this->post('/plugins/weathermapng/maps', [
            'name' => 'duplicate',
            'title' => 'Second Map'
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('name');
        $this->assertEquals(1, Map::where('name', 'duplicate')->count());
    }

    /** @test */
    public function it_displays_map_details_page()
    {
        $map = Map::create(['name' => 'detail-test', 'title' => 'Detail Test Map']);

        $response = $this->get("/plugins/weathermapng/maps/{$map->id}");

        $response->assertStatus(200);
        $response->assertViewIs('plugins.WeathermapNG.show');
        $response->assertViewHas('map');
    }

    /** @test */
    public function it_shows_404_for_nonexistent_map()
    {
        $response = $this->get('/plugins/weathermapng/maps/999');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_displays_map_editor()
    {
        $map = Map::create(['name' => 'editor-test', 'title' => 'Editor Test Map']);

        $response = $this->get("/plugins/weathermapng/maps/{$map->id}/editor");

        $response->assertStatus(200);
        $response->assertViewIs('plugins.WeathermapNG.editor');
        $response->assertViewHas(['map', 'devices']);
    }

    /** @test */
    public function it_can_update_map_properties()
    {
        $map = Map::create([
            'name' => 'update-test',
            'title' => 'Original Title',
            'options' => ['width' => 800, 'height' => 600]
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'width' => 1200,
            'height' => 900
        ];

        $response = $this->put("/plugins/weathermapng/maps/{$map->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $map->refresh();
        $this->assertEquals('Updated Title', $map->title);
        $this->assertEquals(1200, $map->width);
        $this->assertEquals(900, $map->height);
    }

    /** @test */
    public function it_can_delete_a_map()
    {
        $map = Map::create(['name' => 'delete-test', 'title' => 'Delete Test Map']);

        $response = $this->delete("/plugins/weathermapng/maps/{$map->id}");

        $response->assertRedirect('/plugins/weathermapng');
        $this->assertDatabaseMissing('wmng_maps', ['id' => $map->id]);
    }

    /** @test */
    public function it_can_store_nodes_for_a_map()
    {
        $map = Map::create(['name' => 'nodes-test', 'title' => 'Nodes Test Map']);

        $nodeData = [
            'nodes' => [
                [
                    'label' => 'Test Router',
                    'x' => 100,
                    'y' => 100,
                    'device_id' => 1
                ],
                [
                    'label' => 'Test Switch',
                    'x' => 300,
                    'y' => 100,
                    'device_id' => 2
                ]
            ]
        ];

        $response = $this->post("/plugins/weathermapng/maps/{$map->id}/nodes", $nodeData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertEquals(2, $map->nodes()->count());
        $this->assertDatabaseHas('wmng_nodes', [
            'map_id' => $map->id,
            'label' => 'Test Router'
        ]);
    }

    /** @test */
    public function it_can_store_links_for_a_map()
    {
        $map = Map::create(['name' => 'links-test', 'title' => 'Links Test Map']);

        // Create nodes first
        $node1 = Node::create(['map_id' => $map->id, 'label' => 'Node 1', 'x' => 100, 'y' => 100]);
        $node2 = Node::create(['map_id' => $map->id, 'label' => 'Node 2', 'x' => 300, 'y' => 100]);

        $linkData = [
            'links' => [
                [
                    'src_node_id' => $node1->id,
                    'dst_node_id' => $node2->id,
                    'bandwidth_bps' => 1000000000,
                    'style' => ['color' => '#28a745']
                ]
            ]
        ];

        $response = $this->post("/plugins/weathermapng/maps/{$map->id}/links", $linkData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertEquals(1, $map->links()->count());
        $this->assertDatabaseHas('wmng_links', [
            'map_id' => $map->id,
            'src_node_id' => $node1->id,
            'dst_node_id' => $node2->id
        ]);
    }

    /** @test */
    public function it_validates_node_data_when_storing()
    {
        $map = Map::create(['name' => 'validation-test', 'title' => 'Validation Test Map']);

        $invalidNodeData = [
            'nodes' => [
                [
                    'label' => '', // Required field empty
                    'x' => 'not-a-number', // Invalid type
                    'y' => 100
                ]
            ]
        ];

        $response = $this->post("/plugins/weathermapng/maps/{$map->id}/nodes", $invalidNodeData);

        $response->assertStatus(422); // Validation error
        $this->assertEquals(0, $map->nodes()->count());
    }

    /** @test */
    public function it_validates_link_data_when_storing()
    {
        $map = Map::create(['name' => 'link-validation-test', 'title' => 'Link Validation Test']);

        $invalidLinkData = [
            'links' => [
                [
                    'src_node_id' => 999, // Non-existent node
                    'dst_node_id' => 999,
                    'bandwidth_bps' => -100 // Invalid negative value
                ]
            ]
        ];

        $response = $this->post("/plugins/weathermapng/maps/{$map->id}/links", $invalidLinkData);

        $response->assertStatus(422); // Validation error
        $this->assertEquals(0, $map->links()->count());
    }
}