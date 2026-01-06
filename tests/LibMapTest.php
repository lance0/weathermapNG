<?php

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;

class LibMapTest extends TestCase
{
    private function exampleConfig(): string
    {
        return __DIR__ . '/../config/maps/example.conf';
    }

    public function test_map_model_basic_functionality()
    {
        // Skip test if Laravel Eloquent is not available (plugin runs within LibreNMS)
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available in test environment');
        }

        // Test that we can create a Map model instance
        $map = new Map([
            'name' => 'test',
            'title' => 'Example Network Map',
            'options' => ['width' => 800, 'height' => 600]
        ]);

        $this->assertSame('Example Network Map', $map->title);
        $this->assertSame('test', $map->name);
        $this->assertSame(600, $map->height);
        $this->assertSame(800, $map->width);
        $this->assertSame('#ffffff', $map->background);
    }

    public function test_map_to_json_model_structure()
    {
        $this->markTestSkipped('Requires database connection for model relationships');
    }
}

