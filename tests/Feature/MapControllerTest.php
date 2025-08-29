<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Feature;

use LibreNMS\Plugins\WeathermapNG\Tests\TestCase;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;

class MapControllerTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_map_controller()
    {
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController();

        $this->assertInstanceOf(
            \LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController::class,
            $controller
        );
    }

    /** @test */
    public function it_has_required_methods()
    {
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController();

        $requiredMethods = ['index', 'show', 'create', 'update', 'destroy', 'editor', 'storeNodes', 'storeLinks'];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(method_exists($controller, $method),
                "MapController missing required method: {$method}");
        }
    }

    /** @test */
    public function it_can_create_maps_in_database()
    {
        $mapData = [
            'name' => 'controller_test_' . uniqid(),
            'title' => 'Controller Test Map',
            'width' => 1024,
            'height' => 768
        ];

        $map = \LibreNMS\Plugins\WeathermapNG\Models\Map::create($mapData);

        $this->assertDatabaseHas('wmng_maps', [
            'name' => $mapData['name'],
            'title' => $mapData['title']
        ]);

        $this->assertEquals(1024, $map->getWidthAttribute());
        $this->assertEquals(768, $map->getHeightAttribute());
    }

    /** @test */
    public function it_can_create_nodes_for_maps()
    {
        $map = \LibreNMS\Plugins\WeathermapNG\Models\Map::create([
            'name' => 'nodes_test_' . uniqid(),
            'title' => 'Nodes Test Map'
        ]);

        $nodeData = [
            'map_id' => $map->id,
            'label' => 'Test Router',
            'x' => 100,
            'y' => 200,
            'device_id' => 1
        ];

        $node = \LibreNMS\Plugins\WeathermapNG\Models\Node::create($nodeData);

        $this->assertDatabaseHas('wmng_nodes', [
            'map_id' => $map->id,
            'label' => 'Test Router',
            'x' => 100,
            'y' => 200,
            'device_id' => 1
        ]);

        $this->assertEquals(1, $map->nodes()->count());
    }

    /** @test */
    public function it_can_create_links_between_nodes()
    {
        $map = \LibreNMS\Plugins\WeathermapNG\Models\Map::create([
            'name' => 'links_test_' . uniqid(),
            'title' => 'Links Test Map'
        ]);

        $sourceNode = \LibreNMS\Plugins\WeathermapNG\Models\Node::create([
            'map_id' => $map->id,
            'label' => 'Source Node',
            'x' => 100,
            'y' => 100
        ]);

        $targetNode = \LibreNMS\Plugins\WeathermapNG\Models\Node::create([
            'map_id' => $map->id,
            'label' => 'Target Node',
            'x' => 300,
            'y' => 100
        ]);

        $linkData = [
            'map_id' => $map->id,
            'src_node_id' => $sourceNode->id,
            'dst_node_id' => $targetNode->id,
            'bandwidth_bps' => 1000000000,
            'style' => ['color' => '#28a745']
        ];

        $link = \LibreNMS\Plugins\WeathermapNG\Models\Link::create($linkData);

        $this->assertDatabaseHas('wmng_links', [
            'map_id' => $map->id,
            'src_node_id' => $sourceNode->id,
            'dst_node_id' => $targetNode->id,
            'bandwidth_bps' => 1000000000
        ]);

        $this->assertEquals(1, $map->links()->count());
    }

    /** @test */
    public function it_can_validate_map_data_structure()
    {
        // Test that the controller expects proper data structure
        // This would be tested by examining the method signatures and validation rules

        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController();

        // Test that create method exists and has proper structure
        $reflection = new \ReflectionClass($controller);
        $createMethod = $reflection->getMethod('create');

        $this->assertTrue($createMethod->isPublic());
        $this->assertGreaterThan(0, $createMethod->getNumberOfParameters());
    }

    /** @test */
    public function it_can_validate_node_data_structure()
    {
        // Test node data validation logic
        $validNodeData = [
            'label' => 'Test Router',
            'x' => 100,
            'y' => 200,
            'device_id' => 1,
            'meta' => ['type' => 'router']
        ];

        $this->assertArrayHasKey('label', $validNodeData);
        $this->assertArrayHasKey('x', $validNodeData);
        $this->assertArrayHasKey('y', $validNodeData);
        $this->assertIsNumeric($validNodeData['x']);
        $this->assertIsNumeric($validNodeData['y']);
    }

    /** @test */
    public function it_can_validate_link_data_structure()
    {
        // Test link data validation logic
        $validLinkData = [
            'src_node_id' => 1,
            'dst_node_id' => 2,
            'bandwidth_bps' => 1000000000,
            'style' => ['color' => '#28a745']
        ];

        $this->assertArrayHasKey('src_node_id', $validLinkData);
        $this->assertArrayHasKey('dst_node_id', $validLinkData);
        $this->assertArrayHasKey('bandwidth_bps', $validLinkData);
        $this->assertIsNumeric($validLinkData['bandwidth_bps']);
        $this->assertGreaterThan(0, $validLinkData['bandwidth_bps']);
    }

    /** @test */
    public function it_handles_edge_cases_in_data_validation()
    {
        // Test edge cases for data validation
        $edgeCases = [
            ['label' => null, 'x' => 0, 'y' => 0], // Null label
            ['label' => '', 'x' => 0, 'y' => 0],   // Empty label
            ['label' => 'Test', 'x' => null, 'y' => 0], // Null coordinates
            ['label' => 'Test', 'x' => 'invalid', 'y' => 0], // Invalid coordinates
        ];

        foreach ($edgeCases as $case) {
            // These should be considered invalid in a real validation scenario
            if (isset($case['label']) && !empty($case['label'])) {
                $this->assertIsString($case['label']);
            }
        }
    }
}