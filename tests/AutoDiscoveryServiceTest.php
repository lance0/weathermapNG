<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\AutoDiscoveryService;

class AutoDiscoveryServiceTest extends TestCase
{
    private AutoDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AutoDiscoveryService();
    }

    public function test_validate_params_with_empty_input(): void
    {
        $result = $this->service->validateDiscoveryParams([]);

        $this->assertEquals(0, $result['minDegree']);
        $this->assertEmpty($result['osFilter']);
    }

    public function test_validate_params_with_min_degree(): void
    {
        $result = $this->service->validateDiscoveryParams(['min_degree' => '3']);

        $this->assertEquals(3, $result['minDegree']);
    }

    public function test_validate_params_negative_degree_clamped_to_zero(): void
    {
        $result = $this->service->validateDiscoveryParams(['min_degree' => '-5']);

        $this->assertEquals(0, $result['minDegree']);
    }

    public function test_validate_params_with_single_os_filter(): void
    {
        $result = $this->service->validateDiscoveryParams(['os' => 'linux']);

        $this->assertEquals(['linux'], $result['osFilter']);
    }

    public function test_validate_params_with_multiple_os_filters(): void
    {
        $result = $this->service->validateDiscoveryParams(['os' => 'linux,ios,junos']);

        $this->assertEquals(['linux', 'ios', 'junos'], $result['osFilter']);
    }

    public function test_validate_params_trims_whitespace_from_os_filters(): void
    {
        $result = $this->service->validateDiscoveryParams(['os' => ' linux , ios , junos ']);

        $this->assertEquals(['linux', 'ios', 'junos'], $result['osFilter']);
    }

    public function test_validate_params_filters_empty_strings(): void
    {
        $result = $this->service->validateDiscoveryParams(['os' => ',linux,,ios,']);

        $this->assertNotContains('', $result['osFilter']);
        $this->assertContains('linux', $result['osFilter']);
        $this->assertContains('ios', $result['osFilter']);
    }

    public function test_validate_params_with_all_options(): void
    {
        $result = $this->service->validateDiscoveryParams([
            'min_degree' => '2',
            'os' => 'linux,ios',
        ]);

        $this->assertEquals(2, $result['minDegree']);
        $this->assertCount(2, $result['osFilter']);
    }

    public function test_create_link_key_is_deterministic(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createLinkKey');
        $method->setAccessible(true);

        // Same pair should give same key regardless of order
        $key1 = $method->invoke($this->service, 5, 10);
        $key2 = $method->invoke($this->service, 10, 5);

        $this->assertEquals($key1, $key2);
        $this->assertEquals('5-10', $key1);
    }

    public function test_create_link_key_with_same_device(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createLinkKey');
        $method->setAccessible(true);

        $key = $method->invoke($this->service, 7, 7);
        $this->assertEquals('7-7', $key);
    }

    public function test_group_ports_by_device(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('groupPortsByDevice');
        $method->setAccessible(true);

        $ports = [
            ['device_id' => 1, 'ifIndex' => 1],
            ['device_id' => 2, 'ifIndex' => 2],
            ['device_id' => 1, 'ifIndex' => 3],
            ['device_id' => 3, 'ifIndex' => 4],
            ['device_id' => 2, 'ifIndex' => 5],
        ];

        $grouped = $method->invoke($this->service, $ports);

        $this->assertCount(3, $grouped);
        $this->assertCount(2, $grouped[1]);
        $this->assertCount(2, $grouped[2]);
        $this->assertCount(1, $grouped[3]);
    }

    public function test_group_ports_empty_input(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('groupPortsByDevice');
        $method->setAccessible(true);

        $grouped = $method->invoke($this->service, []);
        $this->assertEmpty($grouped);
    }
}
