<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Models\Map;

class MapModelTest extends TestCase
{
    public function test_legacy_dimensions_are_read_when_options_omit_them(): void
    {
        foreach ([null, json_encode(['background' => '#123456'])] as $options) {
            $map = (new Map())->setRawAttributes(['width' => 1173, 'height' => 641, 'options' => $options]);
            $this->assertSame(1173, $map->width);
            $this->assertSame(641, $map->height);
        }
    }

    public function test_options_dimensions_take_precedence_over_legacy_columns(): void
    {
        $map = (new Map())->setRawAttributes([
            'width' => 1173, 'height' => 641,
            'options' => json_encode(['width' => 2048, 'height' => 1024]),
        ]);
        $this->assertSame(2048, $map->width);
        $this->assertSame(1024, $map->height);
    }

    public function test_partial_options_fall_back_per_dimension(): void
    {
        $map = (new Map())->setRawAttributes([
            'width' => 1173, 'height' => 641, 'options' => json_encode(['width' => 2048]),
        ]);
        $this->assertSame(2048, $map->width);
        $this->assertSame(641, $map->height);

        $map->setRawAttributes(['width' => 1173, 'height' => 641, 'options' => json_encode(['height' => 1024])]);
        $this->assertSame(1173, $map->width);
        $this->assertSame(1024, $map->height);
    }

    public function test_absent_dimensions_keep_existing_defaults(): void
    {
        foreach ([[], ['width' => null, 'height' => null]] as $attributes) {
            $map = (new Map())->setRawAttributes($attributes + ['options' => '{}']);
            $this->assertSame(800, $map->width);
            $this->assertSame(600, $map->height);
        }
    }

    public function test_explicit_null_options_dimensions_keep_precedence(): void
    {
        $map = (new Map())->setRawAttributes([
            'width' => 1173, 'height' => 641,
            'options' => json_encode(['width' => null, 'height' => null]),
        ]);
        $this->assertNull($map->width);
        $this->assertNull($map->height);
    }

    public function test_map_has_tags_accessor(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Models/Map.php');
        $this->assertStringContainsString('public function getTagsAttribute()', $content);
        $this->assertStringContainsString("data_get(\$this->options, 'tags', []);", $content);
    }

    public function test_tags_accessor_normalizes_invalid_values(): void
    {
        $reflection = new \ReflectionClass('LibreNMS\\Plugins\\WeathermapNG\\Models\\Map');
        $method = $reflection->getMethod('getTagsAttribute');
        $map = $reflection->newInstanceWithoutConstructor();

        // Set options via reflection
        $prop = $reflection->getProperty('attributes');
        $prop->setAccessible(true);
        $prop->setValue($map, ['options' => json_encode(['tags' => [' core ', '', 'WAN', null, 123]])]);

        $tags = $method->invoke($map);
        $this->assertSame(['core', 'wan'], $tags);
    }

    public function test_tags_accessor_deduplicates(): void
    {
        $reflection = new \ReflectionClass('LibreNMS\\Plugins\\WeathermapNG\\Models\\Map');
        $method = $reflection->getMethod('getTagsAttribute');
        $map = $reflection->newInstanceWithoutConstructor();

        $prop = $reflection->getProperty('attributes');
        $prop->setAccessible(true);
        $prop->setValue($map, ['options' => json_encode(['tags' => ['core', 'Core', 'CORE', 'wan']])]);

        $tags = $method->invoke($map);
        $this->assertSame(['core', 'wan'], $tags);
    }

    public function test_map_has_default_style_accessors(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Models/Map.php');
        $this->assertStringContainsString('public function getDefaultNodeStyleAttribute()', $content);
        $this->assertStringContainsString('public function getDefaultLinkStyleAttribute()', $content);
    }

    public function test_default_node_style_accessor_returns_empty_array_when_absent(): void
    {
        $reflection = new \ReflectionClass('LibreNMS\\Plugins\\WeathermapNG\\Models\\Map');
        $method = $reflection->getMethod('getDefaultNodeStyleAttribute');
        $map = $reflection->newInstanceWithoutConstructor();

        $prop = $reflection->getProperty('attributes');
        $prop->setAccessible(true);
        $prop->setValue($map, ['options' => json_encode(['width' => 800])]);

        $this->assertSame([], $method->invoke($map));
    }

    public function test_default_node_style_accessor_returns_allowed_values(): void
    {
        $reflection = new \ReflectionClass('LibreNMS\\Plugins\\WeathermapNG\\Models\\Map');
        $method = $reflection->getMethod('getDefaultNodeStyleAttribute');
        $map = $reflection->newInstanceWithoutConstructor();

        $prop = $reflection->getProperty('attributes');
        $prop->setAccessible(true);
        $prop->setValue($map, ['options' => json_encode([
            'default_node_style' => ['color' => '#123456', 'label_color' => '#abcdef'],
        ])]);

        $this->assertSame(['color' => '#123456', 'label_color' => '#abcdef'], $method->invoke($map));
    }

    public function test_default_link_style_accessor_returns_allowed_values(): void
    {
        $reflection = new \ReflectionClass('LibreNMS\\Plugins\\WeathermapNG\\Models\\Map');
        $method = $reflection->getMethod('getDefaultLinkStyleAttribute');
        $map = $reflection->newInstanceWithoutConstructor();

        $prop = $reflection->getProperty('attributes');
        $prop->setAccessible(true);
        $prop->setValue($map, ['options' => json_encode([
            'default_link_style' => ['color' => '#333333', 'width' => 3.5, 'via_style' => 'curved'],
        ])]);

        $this->assertSame(['color' => '#333333', 'width' => 3.5, 'via_style' => 'curved'], $method->invoke($map));
    }
}
