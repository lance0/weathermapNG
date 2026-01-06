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

        $api = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\RRD\LibreNMSAPI::class
        );

        $rrdService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\RrdDataService::class
        );

        $snmpService = $this->createMock(
            \LibreNMS\Plugins\WeathermapNG\Services\SnmpDataService::class
        );

        $this->service = new PortUtilService($api, $rrdService, $snmpService);
    }

    public function linkUtilBits_returns_structure_with_no_ports()
    {
        $link = [];

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

    public function service_can_be_instantiated()
    {
        $this->assertInstanceOf(\LibreNMS\Plugins\WeathermapNG\Services\PortUtilService::class, $this->service);
    }

    public function service_has_required_methods()
    {
        $this->assertTrue(method_exists($this->service, 'linkUtilBits'));
        $this->assertTrue(method_exists($this->service, 'getPortData'));
        $this->assertTrue(method_exists($this->service, 'deviceAggregateBits'));
    }

    public function services_are_injected()
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

        $this->assertInstanceOf(\LibreNMS\Plugins\WeathermapNG\Services\PortUtilService::class, $service);
    }
}
