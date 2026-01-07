<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\AlertService;

class AlertServiceTest extends TestCase
{
    private AlertService $alertService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alertService = new AlertService();
    }

    public function test_device_alerts_returns_empty_array_for_empty_input(): void
    {
        $result = $this->alertService->deviceAlerts([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_device_alerts_structure(): void
    {
        if (!class_exists('Illuminate\Support\Facades\DB')) {
            $this->markTestSkipped('DB facade not available');
            return;
        }

        $result = $this->alertService->deviceAlerts([1, 2, 3]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertArrayHasKey(3, $result);
    }

    public function test_device_alerts_returns_alert_structure(): void
    {
        if (!class_exists('Illuminate\Support\Facades\DB')) {
            $this->markTestSkipped('DB facade not available');
            return;
        }

        $result = $this->alertService->deviceAlerts([1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(1, $result);
        $this->assertIsArray($result[1]);
        $this->assertArrayHasKey('count', $result[1]);
        $this->assertArrayHasKey('severity', $result[1]);
        $this->assertIsInt($result[1]['count']);
        $this->assertIsString($result[1]['severity']);
    }

    public function test_port_alerts_returns_empty_array_for_empty_input(): void
    {
        $result = $this->alertService->portAlerts([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_port_alerts_structure(): void
    {
        if (!class_exists('Illuminate\Support\Facades\DB')) {
            $this->markTestSkipped('DB facade not available');
            return;
        }

        $result = $this->alertService->portAlerts([1, 2, 3]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertArrayHasKey(3, $result);
    }

    public function test_port_alerts_returns_alert_structure(): void
    {
        if (!class_exists('Illuminate\Support\Facades\DB')) {
            $this->markTestSkipped('DB facade not available');
            return;
        }

        $result = $this->alertService->portAlerts([1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(1, $result);
        $this->assertIsArray($result[1]);
        $this->assertArrayHasKey('count', $result[1]);
        $this->assertArrayHasKey('severity', $result[1]);
        $this->assertIsInt($result[1]['count']);
        $this->assertIsString($result[1]['severity']);
    }

    public function test_severity_normalization_handles_null(): void
    {
        $method = new \ReflectionMethod($this->alertService, 'normalizeSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->alertService, null);
        $this->assertEquals('warning', $result);
    }

    public function test_severity_normalization_handles_numeric_3(): void
    {
        $method = new \ReflectionMethod($this->alertService, 'normalizeSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->alertService, 3);
        $this->assertEquals('severe', $result);
    }

    public function test_severity_normalization_handles_numeric_2(): void
    {
        $method = new \ReflectionMethod($this->alertService, 'normalizeSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->alertService, 2);
        $this->assertEquals('critical', $result);
    }

    public function test_severity_normalization_handles_numeric_1(): void
    {
        $method = new \ReflectionMethod($this->alertService, 'normalizeSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->alertService, 1);
        $this->assertEquals('warning', $result);
    }

    public function test_severity_normalization_handles_numeric_0(): void
    {
        $method = new \ReflectionMethod($this->alertService, 'normalizeSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->alertService, 0);
        $this->assertEquals('ok', $result);
    }

    public function test_severity_normalization_handles_string_ok(): void
    {
        $method = new \ReflectionMethod($this->alertService, 'normalizeSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->alertService, 'OK');
        $this->assertEquals('ok', $result);
    }

    public function test_severity_normalization_handles_invalid_string(): void
    {
        $method = new \ReflectionMethod($this->alertService, 'normalizeSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->alertService, 'invalid');
        $this->assertEquals('warning', $result);
    }

    public function test_max_severity_compares_correctly(): void
    {
        $method = new \ReflectionMethod($this->alertService, 'maxSeverity');
        $method->setAccessible(true);

        $this->assertEquals('severe', $method->invoke($this->alertService, 'severe', 'critical'));
        $this->assertEquals('critical', $method->invoke($this->alertService, 'critical', 'warning'));
        $this->assertEquals('warning', $method->invoke($this->alertService, 'warning', 'ok'));
    }

    public function test_max_severity_returns_first_if_equal(): void
    {
        $method = new \ReflectionMethod($this->alertService, 'maxSeverity');
        $method->setAccessible(true);

        $this->assertEquals('warning', $method->invoke($this->alertService, 'warning', 'warning'));
        $this->assertEquals('ok', $method->invoke($this->alertService, 'ok', 'ok'));
    }
}
