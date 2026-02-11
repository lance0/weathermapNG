<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;
use LibreNMS\Plugins\WeathermapNG\Services\RrdDataService;

class PortUtilServiceTest extends TestCase
{
    private function createServiceWithMockRrd(array $portDataMap): PortUtilService
    {
        $rrdService = $this->createMock(RrdDataService::class);
        $rrdService->method('getPortTraffic')
            ->willReturnCallback(function ($portId) use ($portDataMap) {
                return $portDataMap[$portId] ?? null;
            });

        return new PortUtilService($rrdService);
    }

    public function test_no_ports_configured(): void
    {
        $service = $this->createServiceWithMockRrd([]);
        $result = $service->linkUtilBits([]);

        $this->assertEquals(0, $result['in_bps']);
        $this->assertEquals(0, $result['out_bps']);
        $this->assertNull($result['pct']);
        $this->assertEquals('No ports configured', $result['err']);
    }

    public function test_single_port_a_with_traffic(): void
    {
        $service = $this->createServiceWithMockRrd([
            101 => ['in' => 500000000, 'out' => 200000000],
        ]);

        $result = $service->linkUtilBits([
            'port_id_a' => 101,
            'port_id_b' => null,
            'bandwidth_bps' => 1000000000, // 1 Gbps
        ]);

        $this->assertEquals(500000000, $result['in_bps']);
        $this->assertEquals(200000000, $result['out_bps']);
        $this->assertEquals(50.0, $result['pct']); // 500M / 1G = 50%
        $this->assertNull($result['err']);
    }

    public function test_both_ports_takes_max(): void
    {
        $service = $this->createServiceWithMockRrd([
            101 => ['in' => 300000000, 'out' => 100000000],
            102 => ['in' => 100000000, 'out' => 400000000],
        ]);

        $result = $service->linkUtilBits([
            'port_id_a' => 101,
            'port_id_b' => 102,
            'bandwidth_bps' => 1000000000,
        ]);

        // in_bps = max(dataA.in=300M, dataB.out=400M) = 400M
        // out_bps = max(dataA.out=100M, dataB.in=100M) = 100M
        $this->assertEquals(400000000, $result['in_bps']);
        $this->assertEquals(100000000, $result['out_bps']);
        // pct = max(400M, 100M) / 1G = 40%
        $this->assertEquals(40.0, $result['pct']);
    }

    public function test_no_bandwidth_returns_null_pct(): void
    {
        $service = $this->createServiceWithMockRrd([
            101 => ['in' => 500000000, 'out' => 200000000],
        ]);

        $result = $service->linkUtilBits([
            'port_id_a' => 101,
            'bandwidth_bps' => null,
        ]);

        $this->assertNull($result['pct']);
        $this->assertNull($result['err']);
    }

    public function test_zero_bandwidth_returns_null_pct(): void
    {
        $service = $this->createServiceWithMockRrd([
            101 => ['in' => 500000000, 'out' => 200000000],
        ]);

        $result = $service->linkUtilBits([
            'port_id_a' => 101,
            'bandwidth_bps' => 0,
        ]);

        $this->assertNull($result['pct']);
    }

    public function test_full_duplex_saturation_is_100_percent(): void
    {
        $service = $this->createServiceWithMockRrd([
            101 => ['in' => 1000000000, 'out' => 1000000000],
        ]);

        $result = $service->linkUtilBits([
            'port_id_a' => 101,
            'bandwidth_bps' => 1000000000,
        ]);

        // max(in, out) / bw = 1G / 1G = 100%
        $this->assertEquals(100.0, $result['pct']);
    }

    public function test_10gbps_link_utilization(): void
    {
        $service = $this->createServiceWithMockRrd([
            101 => ['in' => 8500000000, 'out' => 2000000000],
        ]);

        $result = $service->linkUtilBits([
            'port_id_a' => 101,
            'bandwidth_bps' => 10000000000, // 10 Gbps
        ]);

        // max(8.5G, 2G) / 10G = 85%
        $this->assertEquals(85.0, $result['pct']);
    }

    public function test_port_with_no_rrd_data_returns_zero(): void
    {
        if (!class_exists('Illuminate\Support\Facades\Log')) {
            $this->markTestSkipped('Laravel Log facade not available');
        }

        $service = $this->createServiceWithMockRrd([
            101 => null, // RRD returns null = no data
        ]);

        $result = $service->linkUtilBits([
            'port_id_a' => 101,
            'bandwidth_bps' => 1000000000,
        ]);

        $this->assertEquals(0, $result['in_bps']);
        $this->assertEquals(0, $result['out_bps']);
        $this->assertEquals(0.0, $result['pct']);
    }
}
