<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;
use PHPUnit\Framework\TestCase;

class PortUtilServiceTest extends TestCase
{
    protected PortUtilService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PortUtilService();
    }



    /** @test */
    public function linkUtilBits_returns_structure_with_no_ports()
    {
        $link = [
            // No port_id_a or port_id_b
        ];

        $result = $this->service->linkUtilBits($link);

        $this->assertArrayHasKey('in_bps', $result);
        $this->assertArrayHasKey('out_bps', $result);
        $this->assertArrayHasKey('pct', $result);
        $this->assertArrayHasKey('err', $result);

        $this->assertEquals(0, $result['in_bps']);
        $this->assertEquals(0, $result['out_bps']);
        $this->assertNull($result['pct']);
        $this->assertEquals('No ports configured', $result['err']);
    }

    /** @test */
    public function service_can_be_instantiated()
    {
        $this->assertInstanceOf(\LibreNMS\Plugins\WeathermapNG\Services\PortUtilService::class, $this->service);
    }

    /** @test */
    public function service_has_required_methods()
    {
        $this->assertTrue(method_exists($this->service, 'linkUtilBits'));
        $this->assertTrue(method_exists($this->service, 'getPortData'));
        $this->assertTrue(method_exists($this->service, 'getPortHistory'));
        $this->assertTrue(method_exists($this->service, 'deviceAggregateBits'));
    }

    // Note: Full service tests require Laravel framework and RRD file system access
    // These basic tests verify the service structure and methods exist
    // Comprehensive testing would require Laravel test environment with mocked file system
}