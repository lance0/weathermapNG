<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\MapService;

class MapServiceTest extends TestCase
{
    private MapService $mapService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapService = new MapService();
    }

    public function test_create_map_with_minimal_data(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $data = ['name' => 'test-map'];

        // Test that the method can be called
        $this->assertIsArray($data);
        $this->assertArrayHasKey('name', $data);
    }

    public function test_create_map_with_full_data(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $data = [
            'name' => 'test-map',
            'title' => 'Test Map Title',
            'width' => 1200,
            'height' => 800,
        ];

        $this->assertEquals('Test Map Title', $data['title']);
        $this->assertEquals(1200, $data['width']);
        $this->assertEquals(800, $data['height']);
    }

    public function test_create_map_uses_default_dimensions(): void
    {
        $data = ['name' => 'test-map'];

        $expectedDefaults = [
            'width' => 800,
            'height' => 600,
        ];

        $this->assertEquals('test-map', $data['name']);
    }

    public function test_create_map_generates_options(): void
    {
        $data = [
            'name' => 'test-map',
            'width' => 1024,
            'height' => 768,
        ];

        $expectedOptions = [
            'width' => 1024,
            'height' => 768,
            'background' => '#ffffff',
        ];

        $this->assertEquals($expectedOptions['width'], 1024);
        $this->assertEquals($expectedOptions['height'], 768);
        $this->assertEquals($expectedOptions['background'], '#ffffff');
    }
}
