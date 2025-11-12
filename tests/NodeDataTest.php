<?php

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Models\Node;

class NodeDataTest extends TestCase
{
    public function test_node_model_attributes()
    {
        // Skip test if Laravel Eloquent is not available (plugin runs within LibreNMS)
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available in test environment');
        }

        $node = new Node([
            'id' => 1,
            'map_id' => 1,
            'label' => 'Test Node',
            'x' => 100,
            'y' => 200,
            'device_id' => null,
            'meta' => ['key' => 'value']
        ]);

        $this->assertEquals(1, $node->id);
        $this->assertEquals('Test Node', $node->label);
        $this->assertEquals(100, $node->x);
        $this->assertEquals(200, $node->y);
        $this->assertNull($node->device_id);
        $this->assertEquals(['key' => 'value'], $node->meta);
    }

    public function test_node_status_without_device()
    {
        // Skip test if Laravel Eloquent is not available (plugin runs within LibreNMS)
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available in test environment');
        }

        $node = new Node(['device_id' => null]);
        $this->assertEquals('unknown', $node->status);
    }
}
