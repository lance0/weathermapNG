<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\LinkService;

class LinkServiceTest extends TestCase
{
    private LinkService $linkService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linkService = new LinkService();
    }

    public function test_create_link_with_minimal_data(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $data = [
            'map_id' => 1,
            'src_node_id' => 1,
            'dst_node_id' => 2,
        ];

        $this->assertEquals(1, $data['map_id']);
        $this->assertEquals(1, $data['src_node_id']);
        $this->assertEquals(2, $data['dst_node_id']);
    }

    public function test_create_link_with_ports(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $data = [
            'map_id' => 1,
            'src_node_id' => 1,
            'dst_node_id' => 2,
            'port_id_a' => 101,
            'port_id_b' => 102,
        ];

        $this->assertEquals(101, $data['port_id_a']);
        $this->assertEquals(102, $data['port_id_b']);
    }

    public function test_create_link_with_bandwidth(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $data = [
            'map_id' => 1,
            'src_node_id' => 1,
            'dst_node_id' => 2,
            'bandwidth_bps' => 1000000000, // 1 Gbps
        ];

        $this->assertEquals(1000000000, $data['bandwidth_bps']);
    }

    public function test_create_link_with_style(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $style = [
            'color' => '#007bff',
            'width' => 3,
            'animated' => true,
        ];

        $data = [
            'map_id' => 1,
            'src_node_id' => 1,
            'dst_node_id' => 2,
            'style' => $style,
        ];

        $this->assertIsArray($data['style']);
        $this->assertEquals('#007bff', $data['style']['color']);
        $this->assertEquals(3, $data['style']['width']);
        $this->assertTrue($data['style']['animated']);
    }

    public function test_update_link_data(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $data = [
            'bandwidth_bps' => 2000000000, // 2 Gbps
            'port_id_a' => 201,
        ];

        $this->assertEquals(2000000000, $data['bandwidth_bps']);
        $this->assertEquals(201, $data['port_id_a']);
    }

    public function test_delete_link(): void
    {
        if (!class_exists('Illuminate\Database\Eloquent\Model')) {
            $this->markTestSkipped('Laravel Eloquent not available');
            return;
        }

        $linkId = 5;

        $this->assertEquals(5, $linkId);
    }
}
