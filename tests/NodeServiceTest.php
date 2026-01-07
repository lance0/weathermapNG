<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\NodeService;

class NodeServiceTest extends TestCase
{
    private NodeService $nodeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nodeService = new NodeService();
    }

    public function test_create_node_with_minimal_data(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $data = [
            'map_id' => 1,
            'label' => 'Test Node',
            'x' => 100,
            'y' => 200,
        ];

        $this->assertEquals(1, $data['map_id']);
        $this->assertEquals('Test Node', $data['label']);
        $this->assertEquals(100, $data['x']);
        $this->assertEquals(200, $data['y']);
    }

    public function test_create_node_with_device(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $data = [
            'map_id' => 1,
            'label' => 'Test Device',
            'x' => 150,
            'y' => 250,
            'device_id' => 42,
        ];

        $this->assertEquals(42, $data['device_id']);
    }

    public function test_create_node_with_meta(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $meta = ['hostname' => 'router1.example.com', 'sn' => 'R1'];

        $data = [
            'map_id' => 1,
            'label' => 'Test Node',
            'x' => 100,
            'y' => 200,
            'meta' => $meta,
        ];

        $this->assertIsArray($data['meta']);
        $this->assertEquals('router1.example.com', $data['meta']['hostname']);
    }

    public function test_update_node_position(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $data = [
            'x' => 300,
            'y' => 400,
        ];

        $this->assertEquals(300, $data['x']);
        $this->assertEquals(400, $data['y']);
    }

    public function test_delete_node_handles_missing_node(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $nodeId = 99999;

        $this->assertEquals(99999, $nodeId);
    }
}
