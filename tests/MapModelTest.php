<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class MapModelTest extends TestCase
{
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
}
