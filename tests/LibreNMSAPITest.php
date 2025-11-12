<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\RRD\LibreNMSAPI;
use PHPUnit\Framework\TestCase;

class LibreNMSAPITest extends TestCase
{
    protected LibreNMSAPI $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new LibreNMSAPI();
    }

    /** @test */
    public function api_can_be_instantiated()
    {
        $this->assertInstanceOf(LibreNMSAPI::class, $this->api);
    }

    /** @test */
    public function api_has_required_methods()
    {
        $this->assertTrue(method_exists($this->api, 'getPortMetricByPortId'));
        $this->assertTrue(method_exists($this->api, 'getPortData'));
        $this->assertTrue(method_exists($this->api, 'getDeviceData'));
    }

    /** @test */
    public function getPortMetricByPortId_returns_array_structure()
    {
        // This will likely fail due to missing config, but we can test the return type
        $result = $this->api->getPortMetricByPortId(1, 'traffic_in');

        // Should return an array even on error
        $this->assertIsArray($result);
    }

    /** @test */
    public function getPortData_returns_array_structure()
    {
        $result = $this->api->getPortData(1, 1, 'traffic_in');

        // Should return an array even on error
        $this->assertIsArray($result);
    }

    /** @test */
    public function getDeviceData_returns_array_structure()
    {
        $result = $this->api->getDeviceData(1, 'traffic_in');

        // Should return an array even on error
        $this->assertIsArray($result);
    }

    // Note: Full API testing requires LibreNMS server and valid API tokens
    // These basic tests verify the class structure and method signatures
    // Comprehensive testing would require mocked HTTP responses and API configuration
}