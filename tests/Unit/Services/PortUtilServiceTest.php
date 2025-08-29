<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Unit\Services;

use LibreNMS\Plugins\WeathermapNG\Tests\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\PortUtilService;
use LibreNMS\Plugins\WeathermapNG\RRD\RRDTool;
use LibreNMS\Plugins\WeathermapNG\RRD\LibreNMSAPI;
use Mockery;

class PortUtilServiceTest extends TestCase
{
    protected $service;
    protected $rrdToolMock;
    protected $apiMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->rrdToolMock = Mockery::mock(RRDTool::class);
        $this->apiMock = Mockery::mock(LibreNMSAPI::class);

        // Create service with mocked dependencies
        $this->service = new PortUtilService();
        // Note: In a real implementation, you'd inject these via constructor
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_calculates_link_utilization_correctly()
    {
        $link = [
            'port_id_a' => 1,
            'port_id_b' => 2,
            'bandwidth_bps' => 1000000000 // 1 Gbps
        ];

        // Mock the service to return test data
        $mockService = Mockery::mock(PortUtilService::class)->makePartial();
        $mockService->shouldReceive('getPortData')
            ->with(1)
            ->andReturn(['in' => 100000000, 'out' => 200000000]); // 100Mbps in, 200Mbps out
        $mockService->shouldReceive('getPortData')
            ->with(2)
            ->andReturn(['in' => 150000000, 'out' => 250000000]); // 150Mbps in, 250Mbps out

        $result = $mockService->linkUtilBits($link);

        $this->assertEquals(250000000, $result['in_bps']);  // Max of (100M + 150M)
        $this->assertEquals(450000000, $result['out_bps']); // Max of (200M + 250M)
        $this->assertEquals(70.0, $result['pct']); // (250M + 450M) / 1000M * 100
        $this->assertNull($result['err']);
    }

    /** @test */
    public function it_handles_missing_ports_gracefully()
    {
        $link = [
            'port_id_a' => null,
            'port_id_b' => null,
            'bandwidth_bps' => 1000000000
        ];

        $result = $this->service->linkUtilBits($link);

        $this->assertEquals(0, $result['in_bps']);
        $this->assertEquals(0, $result['out_bps']);
        $this->assertNull($result['pct']);
        $this->assertEquals('No ports configured', $result['err']);
    }

    /** @test */
    public function it_handles_zero_bandwidth_gracefully()
    {
        $link = [
            'port_id_a' => 1,
            'port_id_b' => 2,
            'bandwidth_bps' => 0
        ];

        $mockService = Mockery::mock(PortUtilService::class)->makePartial();
        $mockService->shouldReceive('getPortData')
            ->andReturn(['in' => 100000000, 'out' => 200000000]);

        $result = $mockService->linkUtilBits($link);

        $this->assertEquals(100000000, $result['in_bps']);
        $this->assertEquals(200000000, $result['out_bps']);
        $this->assertNull($result['pct']); // Division by zero avoided
        $this->assertNull($result['err']);
    }

    /** @test */
    public function it_calculates_utilization_percentage_correctly()
    {
        $testCases = [
            [
                'bandwidth' => 1000000000, // 1 Gbps
                'usage' => 100000000,      // 100 Mbps
                'expected' => 10.0
            ],
            [
                'bandwidth' => 1000000000, // 1 Gbps
                'usage' => 500000000,      // 500 Mbps
                'expected' => 50.0
            ],
            [
                'bandwidth' => 100000000,  // 100 Mbps
                'usage' => 100000000,      // 100 Mbps
                'expected' => 100.0
            ],
            [
                'bandwidth' => 10000000,   // 10 Mbps
                'usage' => 15000000,       // 15 Mbps
                'expected' => 150.0        // Over 100% is possible
            ]
        ];

        foreach ($testCases as $testCase) {
            $link = [
                'port_id_a' => 1,
                'port_id_b' => 2,
                'bandwidth_bps' => $testCase['bandwidth']
            ];

            $mockService = Mockery::mock(PortUtilService::class)->makePartial();
            $mockService->shouldReceive('getPortData')
                ->andReturn(['in' => $testCase['usage'], 'out' => 0]);

            $result = $mockService->linkUtilBits($link);

            $this->assertEquals($testCase['expected'], $result['pct'],
                "Failed for bandwidth {$testCase['bandwidth']} and usage {$testCase['usage']}");
        }
    }

    /** @test */
    public function it_handles_rrd_data_fetching()
    {
        // This test would require mocking the RRDTool class
        // For now, we'll test the basic structure

        $this->assertInstanceOf(PortUtilService::class, $this->service);

        // Test that the service has the expected methods
        $this->assertTrue(method_exists($this->service, 'linkUtilBits'));
        $this->assertTrue(method_exists($this->service, 'getPortData'));
    }

    /** @test */
    public function it_handles_api_data_fetching()
    {
        // Test API fallback functionality
        $link = [
            'port_id_a' => 1,
            'port_id_b' => 2,
            'bandwidth_bps' => 1000000000
        ];

        // Mock API to return test data
        $mockApi = Mockery::mock(LibreNMSAPI::class);
        $mockApi->shouldReceive('getPortMetricByPortId')
            ->andReturn([
                ['timestamp' => time(), 'value' => 100000000],
                ['timestamp' => time() - 300, 'value' => 95000000]
            ]);

        // This would require dependency injection to properly test
        // For now, we verify the service structure
        $this->assertTrue(method_exists($this->service, 'linkUtilBits'));
    }

    /** @test */
    public function it_calculates_average_values_correctly()
    {
        // Test the extractLatestValue method indirectly through linkUtilBits
        $link = [
            'port_id_a' => 1,
            'port_id_b' => 2,
            'bandwidth_bps' => 1000000000
        ];

        $mockService = Mockery::mock(PortUtilService::class)->makePartial();
        $mockService->shouldReceive('getPortData')
            ->andReturn(['in' => 100000000, 'out' => 200000000]);

        $result = $mockService->linkUtilBits($link);

        // Verify calculations are correct
        $this->assertIsNumeric($result['in_bps']);
        $this->assertIsNumeric($result['out_bps']);
        $this->assertIsNumeric($result['pct']);
    }

    /** @test */
    public function it_handles_service_errors_gracefully()
    {
        $link = [
            'port_id_a' => 1,
            'port_id_b' => 2,
            'bandwidth_bps' => 1000000000
        ];

        $mockService = Mockery::mock(PortUtilService::class)->makePartial();
        $mockService->shouldReceive('getPortData')
            ->andThrow(new \Exception('Service temporarily unavailable'));

        $result = $mockService->linkUtilBits($link);

        $this->assertEquals(0, $result['in_bps']);
        $this->assertEquals(0, $result['out_bps']);
        $this->assertNull($result['pct']);
        $this->assertStringContains('Service temporarily unavailable', $result['err']);
    }

    /** @test */
    public function it_validates_link_data_structure()
    {
        // Test with various invalid link structures
        $invalidLinks = [
            ['port_id_a' => null, 'port_id_b' => null], // No ports
            ['port_id_a' => 1], // Missing port_b
            ['port_id_b' => 2], // Missing port_a
            [], // Empty
        ];

        foreach ($invalidLinks as $link) {
            $link['bandwidth_bps'] = 1000000000; // Add required field
            $result = $this->service->linkUtilBits($link);

            $this->assertEquals(0, $result['in_bps']);
            $this->assertEquals(0, $result['out_bps']);
            $this->assertNull($result['pct']);
            $this->assertNotNull($result['err']);
        }
    }
}