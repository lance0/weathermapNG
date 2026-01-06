<?php

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;

class LinkUtilizationTest extends TestCase
{
    public function test_link_utilization_calculation()
    {
        $api = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\RRD\LibreNMSAPI::class
        );

        $rrdService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\RrdDataService::class
        );

        $snmpService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\SnmpDataService::class
        );

        $service = new PortUtilService($api, $rrdService, $snmpService);

        $link = [
            'port_id_a' => 1,
            'port_id_b' => 2,
            'bandwidth_bps' => 1000,
        ];

        $result = $service->linkUtilBits($link);

        $this->assertArrayHasKey('in_bps', $result);
        $this->assertArrayHasKey('out_bps', $result);
        $this->assertArrayHasKey('pct', $result);
        $this->assertArrayHasKey('err', $result);
    }

    public function test_link_utilization_with_no_ports()
    {
        $api = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\RRD\LibreNMSAPI::class
        );

        $rrdService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\RrdDataService::class
        );

        $snmpService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\SnmpDataService::class
        );

        $service = new PortUtilService($api, $rrdService, $snmpService);

        $link = [
            'bandwidth_bps' => 1000,
        ];

        $result = $service->linkUtilBits($link);

        $this->assertEquals(0, $result['in_bps']);
        $this->assertEquals(0, $result['out_bps']);
        $this->assertNull($result['pct']);
        $this->assertEquals('No ports configured', $result['err']);
    }

    public function test_service_has_required_methods()
    {
        $service = $this->createMock(PortUtilService::class);

        $this->assertTrue(method_exists($service, 'linkUtilBits'));
        $this->assertTrue(method_exists($service, 'getPortData'));
        $this->assertTrue(method_exists($service, 'deviceAggregateBits'));
    }
}
