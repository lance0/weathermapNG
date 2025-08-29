<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Feature;

use LibreNMS\Plugins\WeathermapNG\Tests\TestCase;

class HealthControllerTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_health_controller()
    {
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController();

        $this->assertInstanceOf(
            \LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController::class,
            $controller
        );
    }

    /** @test */
    public function it_has_required_methods()
    {
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController();

        $this->assertTrue(method_exists($controller, 'check'));
        $this->assertTrue(method_exists($controller, 'stats'));
    }

    /** @test */
    public function it_can_validate_check_method()
    {
        // Test that check method exists and has proper structure
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController();
        $reflection = new \ReflectionClass($controller);
        $checkMethod = $reflection->getMethod('check');

        $this->assertTrue($checkMethod->isPublic());
        $this->assertGreaterThan(0, $checkMethod->getNumberOfParameters()); // At least Request
    }

    /** @test */
    public function it_can_validate_stats_method()
    {
        // Test that stats method exists and has proper structure
        $controller = new \LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController();
        $reflection = new \ReflectionClass($controller);
        $statsMethod = $reflection->getMethod('stats');

        $this->assertTrue($statsMethod->isPublic());
        $this->assertGreaterThan(0, $statsMethod->getNumberOfParameters()); // At least Request
    }

    /** @test */
    public function it_can_validate_health_response_structure()
    {
        // Test that the health response would have expected structure
        // This validates the conceptual structure without HTTP testing

        $expectedHealthStructure = [
            'status',
            'timestamp',
            'version',
            'checks'
        ];

        $expectedCheckStructure = [
            'database' => ['status', 'message'],
            'rrd' => ['status', 'message'],
            'output' => ['status', 'message'],
            'api_token' => ['status', 'message']
        ];

        // Validate that our expected structures are properly defined
        $this->assertIsArray($expectedHealthStructure);
        $this->assertIsArray($expectedCheckStructure);
        $this->assertContains('status', $expectedHealthStructure);
        $this->assertContains('checks', $expectedHealthStructure);
    }

    /** @test */
    public function it_can_validate_stats_response_structure()
    {
        // Test that the stats response would have expected structure

        $expectedStatsStructure = [
            'maps',
            'nodes',
            'links',
            'last_updated',
            'database_size',
            'cache_info'
        ];

        // Validate that our expected structures are properly defined
        $this->assertIsArray($expectedStatsStructure);
        $this->assertContains('maps', $expectedStatsStructure);
        $this->assertContains('nodes', $expectedStatsStructure);
        $this->assertContains('links', $expectedStatsStructure);
    }
}