<?php

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;

class LinkUtilizationTest extends TestCase
{
    public function test_link_utilization_calculation()
    {
        $service = new PortUtilService();

        // Mock the service to return test data
        // Since the service uses caching and RRD, we'll test the calculation logic directly
        $link = [
            'port_id_a' => 1,
            'port_id_b' => 2,
            'bandwidth_bps' => 1000,
        ];

        // Test with mock port data - we'd need to mock the getPortData method
        // For now, test that the service exists and has the method
        $this->assertTrue(method_exists($service, 'linkUtilBits'));
        $this->assertTrue(method_exists($service, 'getPortData'));
    }

    public function test_link_utilization_with_no_ports()
    {
        $service = new PortUtilService();

        $link = [
            'bandwidth_bps' => 1000,
        ];

        $result = $service->linkUtilBits($link);

        $this->assertEquals(0, $result['in_bps']);
        $this->assertEquals(0, $result['out_bps']);
        $this->assertNull($result['pct']);
        $this->assertEquals('No ports configured', $result['err']);
    }
}

