<?php

use PHPUnit\Framework\TestCase;

class RoutesSmokeTest extends TestCase
{
    public function testControllersExist()
    {
        $this->assertTrue(class_exists('LibreNMS\\Plugins\\WeathermapNG\\Http\\Controllers\\RenderController'));
        $this->assertTrue(class_exists('LibreNMS\\Plugins\\WeathermapNG\\Http\\Controllers\\MapController'));
        $this->assertTrue(class_exists('LibreNMS\\Plugins\\WeathermapNG\\Http\\Controllers\\HealthController'));
    }

    public function testRoutesFileContainsExpectedPaths()
    {
        $routes = file_get_contents(__DIR__ . '/../routes.php');
        $this->assertStringContainsString("prefix' => 'plugin/WeathermapNG'", $routes);
        $this->assertStringContainsString("/embed/{map}", $routes);
        $this->assertStringContainsString("/api/maps/{map}/json", $routes);
        $this->assertStringContainsString("/api/maps/{map}/live", $routes);
        $this->assertStringContainsString("/api/maps/{map}/export", $routes);
        $this->assertStringContainsString("/api/maps/{map}/sse", $routes);
        $this->assertStringContainsString("/api/import", $routes);
        $this->assertStringContainsString("/health", $routes);
        $this->assertStringContainsString("/health/stats", $routes);
        $this->assertStringContainsString("/metrics", $routes);
        // Optional CRUD entries appear in the file
        $this->assertStringContainsString("/map/{map}", $routes);
        $this->assertStringContainsString("/map", $routes);
        // Device/ports lookup endpoints
        $this->assertStringContainsString("/api/devices", $routes);
        $this->assertStringContainsString("/api/device/{id}/ports", $routes);
        // Editor save endpoints
        $this->assertStringContainsString("/api/maps/{map}/save", $routes);
        $this->assertStringContainsString("/map/{map}/node/{node}", $routes);
        $this->assertStringContainsString("/map/{map}/link/{link}", $routes);
        $this->assertStringContainsString("/map/{map}/node", $routes);
        $this->assertStringContainsString("/map/{map}/link", $routes);
    }
}
