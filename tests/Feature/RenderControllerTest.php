<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Feature;

use LibreNMS\Plugins\WeathermapNG\Tests\TestCase;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;

class RenderControllerTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_render_controller()
    {
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController();

        $this->assertInstanceOf(
            \LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController::class,
            $controller
        );
    }

    /** @test */
    public function it_has_required_methods()
    {
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController();

        $requiredMethods = ['json', 'live', 'embed', 'export', 'import'];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(method_exists($controller, $method),
                "RenderController missing required method: {$method}");
        }
    }

    /** @test */
    public function it_can_generate_map_json_data()
    {
        $map = \LibreNMS\Plugins\WeathermapNG\Models\Map::create([
            'name' => 'json_render_test_' . uniqid(),
            'title' => 'JSON Render Test',
            'options' => ['width' => 800, 'height' => 600]
        ]);

        $node = \LibreNMS\Plugins\WeathermapNG\Models\Node::create([
            'map_id' => $map->id,
            'label' => 'Test Node',
            'x' => 100,
            'y' => 200
        ]);

        // Test that the map can generate JSON data
        $jsonData = $map->toJsonModel();

        $this->assertIsArray($jsonData);
        $this->assertEquals($map->name, $jsonData['name']);
        $this->assertEquals($map->title, $jsonData['title']);
        $this->assertCount(1, $jsonData['nodes']);
        $this->assertEquals('Test Node', $jsonData['nodes'][0]['label']);
    }

    /** @test */
    public function it_can_handle_empty_maps()
    {
        $map = \LibreNMS\Plugins\WeathermapNG\Models\Map::create([
            'name' => 'empty_render_test_' . uniqid(),
            'title' => 'Empty Render Test'
        ]);

        $jsonData = $map->toJsonModel();

        $this->assertIsArray($jsonData);
        $this->assertEmpty($jsonData['nodes']);
        $this->assertEmpty($jsonData['links']);
        $this->assertEquals(0, $jsonData['metadata']['total_nodes']);
        $this->assertEquals(0, $jsonData['metadata']['total_links']);
    }

    /** @test */
    public function it_can_validate_json_structure_requirements()
    {
        // Test that the json method expects a Map instance
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController();
        $reflection = new \ReflectionClass($controller);
        $jsonMethod = $reflection->getMethod('json');

        $parameters = $jsonMethod->getParameters();
        $this->assertGreaterThan(0, count($parameters));

        // The first parameter should be a Map type
        $firstParam = $parameters[0];
        $this->assertEquals('map', $firstParam->getName());
    }

    /** @test */
    public function it_can_validate_export_functionality()
    {
        // Test that export method exists and has proper parameters
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController();
        $reflection = new \ReflectionClass($controller);
        $exportMethod = $reflection->getMethod('export');

        $this->assertTrue($exportMethod->isPublic());
        $this->assertGreaterThan(1, $exportMethod->getNumberOfParameters()); // Map + Request
    }

    /** @test */
    public function it_can_validate_import_functionality()
    {
        // Test that import method exists and has proper parameters
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController();
        $reflection = new \ReflectionClass($controller);
        $importMethod = $reflection->getMethod('import');

        $this->assertTrue($importMethod->isPublic());
        $this->assertGreaterThan(0, $importMethod->getNumberOfParameters()); // At least Request
    }

    /** @test */
    public function it_can_validate_embed_functionality()
    {
        // Test that embed method exists and has proper parameters
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController();
        $reflection = new \ReflectionClass($controller);
        $embedMethod = $reflection->getMethod('embed');

        $this->assertTrue($embedMethod->isPublic());
        $this->assertGreaterThan(0, $embedMethod->getNumberOfParameters()); // At least Map
    }

    /** @test */
    public function it_can_validate_live_data_functionality()
    {
        // Test that live method exists and has proper parameters
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController();
        $reflection = new \ReflectionClass($controller);
        $liveMethod = $reflection->getMethod('live');

        $this->assertTrue($liveMethod->isPublic());
        $this->assertGreaterThan(1, $liveMethod->getNumberOfParameters()); // Map + Service
    }
}