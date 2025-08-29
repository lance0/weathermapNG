<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Feature;

use LibreNMS\Plugins\WeathermapNG\Tests\TestCase;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;

class RenderControllerTest extends TestCase
{
    /** @test */
    public function it_returns_map_json_structure()
    {
        $map = $this->createTestMap(['name' => 'json-test'], 2, 1);

        $response = $this->get("/plugins/weathermapng/api/maps/{$map->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'id',
                    'name',
                    'title',
                    'width',
                    'height',
                    'background',
                    'options',
                    'nodes' => [
                        '*' => [
                            'id',
                            'label',
                            'x',
                            'y',
                            'device_id',
                            'device_name',
                            'status',
                            'meta'
                        ]
                    ],
                    'links' => [
                        '*' => [
                            'id',
                            'src',
                            'dst',
                            'bandwidth_bps',
                            'source_port_name',
                            'destination_port_name',
                            'bandwidth_formatted',
                            'style'
                        ]
                    ],
                    'metadata' => [
                        'total_nodes',
                        'total_links',
                        'last_updated'
                    ]
                ]);

        $responseData = $response->json();
        $this->assertEquals('json-test', $responseData['name']);
        $this->assertCount(2, $responseData['nodes']);
        $this->assertCount(1, $responseData['links']);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_map()
    {
        $response = $this->get('/plugins/weathermapng/api/maps/999');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_returns_live_utilization_data()
    {
        $map = $this->createTestMap([], 2, 1);

        // Mock the PortUtilService to return predictable data
        $this->mock(PortUtilService::class, function ($mock) {
            $mock->shouldReceive('linkUtilBits')
                ->andReturn([
                    'in_bps' => 50000000,
                    'out_bps' => 75000000,
                    'pct' => 12.5,
                    'err' => null
                ]);
        });

        $response = $this->get("/plugins/weathermapng/api/maps/{$map->id}/live");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'ts',
                    'links' => [
                        '*' => [
                            'in_bps',
                            'out_bps',
                            'pct',
                            'err'
                        ]
                    ]
                ]);

        $responseData = $response->json();
        $this->assertIsInt($responseData['ts']);
        $this->assertArrayHasKey($map->links()->first()->id, $responseData['links']);
    }

    /** @test */
    public function it_provides_embeddable_map_view()
    {
        $map = $this->createTestMap(['name' => 'embed-test'], 2, 1);

        $response = $this->get("/plugins/weathermapng/embed/{$map->id}");

        $response->assertStatus(200);
        $response->assertViewIs('plugins.WeathermapNG.embed');
        $response->assertViewHas(['map', 'mapData']);

        // Check that the view contains expected elements
        $response->assertSee('weathermapng-viewer');
        $response->assertSee('map-canvas');
        $response->assertSee('WeathermapNG - embed-test');
    }

    /** @test */
    public function it_can_export_map_as_json()
    {
        $map = $this->createTestMap(['name' => 'export-test'], 3, 2);

        $response = $this->get("/plugins/weathermapng/api/maps/{$map->id}/export?format=json");

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename="export-test.json"');

        $exportData = $response->json();
        $this->assertEquals('export-test', $exportData['name']);
        $this->assertCount(3, $exportData['nodes']);
        $this->assertCount(2, $exportData['links']);
    }

    /** @test */
    public function it_validates_import_data_structure()
    {
        $map = $this->createTestMap();

        // Test invalid import data
        $invalidData = [
            'nodes' => 'not-an-array', // Should be array
            'links' => []
        ];

        $response = $this->post("/plugins/weathermapng/api/maps/import", $invalidData);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_imports_valid_map_data()
    {
        $importData = [
            'name' => 'imported-map',
            'title' => 'Imported Map',
            'options' => ['width' => 1000, 'height' => 800],
            'nodes' => [
                [
                    'label' => 'Imported Router',
                    'x' => 200,
                    'y' => 150,
                    'device_id' => 1,
                    'meta' => ['type' => 'router']
                ],
                [
                    'label' => 'Imported Switch',
                    'x' => 500,
                    'y' => 150,
                    'device_id' => 2,
                    'meta' => ['type' => 'switch']
                ]
            ],
            'links' => [
                [
                    'src' => 0, // Will be mapped to actual node ID
                    'dst' => 1,
                    'bandwidth_bps' => 1000000000,
                    'style' => ['color' => '#28a745']
                ]
            ]
        ];

        $response = $this->post('/plugins/weathermapng/api/maps/import', [
            'file' => json_encode($importData)
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'imported_nodes' => 2,
                    'imported_links' => 1
                ]);

        $this->assertDatabaseHas('wmng_maps', ['name' => 'imported-map']);
        $this->assertDatabaseHas('wmng_nodes', ['label' => 'Imported Router']);
        $this->assertDatabaseHas('wmng_nodes', ['label' => 'Imported Switch']);
    }

    /** @test */
    public function it_handles_empty_maps_gracefully()
    {
        $map = Map::create(['name' => 'empty-map', 'title' => 'Empty Map']);

        $response = $this->get("/plugins/weathermapng/api/maps/{$map->id}");

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertEquals('empty-map', $responseData['name']);
        $this->assertEmpty($responseData['nodes']);
        $this->assertEmpty($responseData['links']);
        $this->assertEquals(0, $responseData['metadata']['total_nodes']);
        $this->assertEquals(0, $responseData['metadata']['total_links']);
    }

    /** @test */
    public function it_includes_device_and_port_information_in_response()
    {
        $map = $this->createTestMap([], 2, 1);
        $link = $map->links()->first();

        // Update link with port information
        $link->update([
            'port_id_a' => 100,
            'port_id_b' => 200
        ]);

        $response = $this->get("/plugins/weathermapng/api/maps/{$map->id}");

        $response->assertStatus(200);

        $responseData = $response->json();
        $responseLink = $responseData['links'][0];

        $this->assertEquals(100, $responseLink['port_id_a']);
        $this->assertEquals(200, $responseLink['port_id_b']);
        $this->assertEquals('1 Gbps', $responseLink['bandwidth_formatted']);
    }

    /** @test */
    public function it_handles_service_unavailable_gracefully()
    {
        $map = $this->createTestMap();

        // Mock service to throw exception
        $this->mock(PortUtilService::class, function ($mock) {
            $mock->shouldReceive('linkUtilBits')
                ->andThrow(new \Exception('Service unavailable'));
        });

        $response = $this->get("/plugins/weathermapng/api/maps/{$map->id}/live");

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertIsInt($responseData['ts']);
        $this->assertIsArray($responseData['links']);
    }
}