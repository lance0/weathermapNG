<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\AlertService;

class AlertServiceTest extends TestCase
{
    private AlertService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AlertService();
    }

    // --- normalizeSeverity tests (private, tested via reflection) ---

    private function normalizeSeverity($input): string
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normalizeSeverity');
        $method->setAccessible(true);
        return $method->invoke($this->service, $input);
    }

    public function test_normalize_null_returns_warning(): void
    {
        $this->assertEquals('warning', $this->normalizeSeverity(null));
    }

    public function test_normalize_numeric_zero_returns_ok(): void
    {
        $this->assertEquals('ok', $this->normalizeSeverity(0));
    }

    public function test_normalize_numeric_one_returns_warning(): void
    {
        $this->assertEquals('warning', $this->normalizeSeverity(1));
    }

    public function test_normalize_numeric_two_returns_critical(): void
    {
        $this->assertEquals('critical', $this->normalizeSeverity(2));
    }

    public function test_normalize_numeric_three_returns_severe(): void
    {
        $this->assertEquals('severe', $this->normalizeSeverity(3));
    }

    public function test_normalize_high_numeric_returns_severe(): void
    {
        $this->assertEquals('severe', $this->normalizeSeverity(99));
    }

    public function test_normalize_string_ok(): void
    {
        $this->assertEquals('ok', $this->normalizeSeverity('ok'));
    }

    public function test_normalize_string_warning(): void
    {
        $this->assertEquals('warning', $this->normalizeSeverity('warning'));
    }

    public function test_normalize_string_critical(): void
    {
        $this->assertEquals('critical', $this->normalizeSeverity('critical'));
    }

    public function test_normalize_string_severe(): void
    {
        $this->assertEquals('severe', $this->normalizeSeverity('severe'));
    }

    public function test_normalize_string_case_insensitive(): void
    {
        $this->assertEquals('critical', $this->normalizeSeverity('CRITICAL'));
        $this->assertEquals('warning', $this->normalizeSeverity('Warning'));
    }

    public function test_normalize_unknown_string_returns_warning(): void
    {
        $this->assertEquals('warning', $this->normalizeSeverity('unknown'));
        $this->assertEquals('warning', $this->normalizeSeverity('info'));
        $this->assertEquals('warning', $this->normalizeSeverity('banana'));
    }

    // --- maxSeverity tests ---

    private function maxSeverity(string $a, string $b): string
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('maxSeverity');
        $method->setAccessible(true);
        return $method->invoke($this->service, $a, $b);
    }

    public function test_max_severity_returns_higher(): void
    {
        $this->assertEquals('critical', $this->maxSeverity('warning', 'critical'));
        $this->assertEquals('critical', $this->maxSeverity('critical', 'warning'));
    }

    public function test_max_severity_same_returns_same(): void
    {
        $this->assertEquals('warning', $this->maxSeverity('warning', 'warning'));
    }

    public function test_max_severity_severe_beats_all(): void
    {
        $this->assertEquals('severe', $this->maxSeverity('ok', 'severe'));
        $this->assertEquals('severe', $this->maxSeverity('critical', 'severe'));
    }

    public function test_max_severity_ok_loses_to_all(): void
    {
        $this->assertEquals('warning', $this->maxSeverity('ok', 'warning'));
        $this->assertEquals('critical', $this->maxSeverity('ok', 'critical'));
        $this->assertEquals('severe', $this->maxSeverity('ok', 'severe'));
    }

    public function test_max_severity_ordering(): void
    {
        $levels = ['ok', 'warning', 'critical', 'severe'];
        for ($i = 0; $i < count($levels); $i++) {
            for ($j = $i; $j < count($levels); $j++) {
                $this->assertEquals(
                    $levels[$j],
                    $this->maxSeverity($levels[$i], $levels[$j]),
                    "{$levels[$j]} should beat {$levels[$i]}"
                );
            }
        }
    }

    // --- buildAlertsByDevice tests ---

    public function test_device_alerts_returns_empty_for_empty_input(): void
    {
        $result = $this->service->deviceAlerts([]);
        $this->assertEmpty($result);
    }

    public function test_port_alerts_returns_empty_for_empty_input(): void
    {
        $result = $this->service->portAlerts([]);
        $this->assertEmpty($result);
    }

    // --- buildAlertsByDevice via reflection ---

    public function test_build_alerts_initializes_all_devices(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildAlertsByDevice');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [], [1, 2, 3]);

        $this->assertCount(3, $result);
        foreach ([1, 2, 3] as $id) {
            $this->assertEquals(0, $result[$id]['count']);
            $this->assertEquals('warning', $result[$id]['severity']);
        }
    }

    public function test_build_alerts_counts_per_device(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildAlertsByDevice');
        $method->setAccessible(true);

        $alerts = [
            ['device_id' => 1, 'severity' => 1],
            ['device_id' => 1, 'severity' => 2],
            ['device_id' => 2, 'severity' => 1],
        ];

        $result = $method->invoke($this->service, $alerts, [1, 2]);

        $this->assertEquals(2, $result[1]['count']);
        $this->assertEquals('critical', $result[1]['severity']);
        $this->assertEquals(1, $result[2]['count']);
        $this->assertEquals('warning', $result[2]['severity']);
    }

    public function test_build_alerts_ignores_unknown_devices(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildAlertsByDevice');
        $method->setAccessible(true);

        $alerts = [
            ['device_id' => 999, 'severity' => 3],
        ];

        $result = $method->invoke($this->service, $alerts, [1]);

        $this->assertEquals(0, $result[1]['count']);
    }

    // --- buildAlertsByPort via reflection ---

    public function test_build_port_alerts_initializes_all_ports(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildAlertsByPort');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [], [10, 20]);

        $this->assertCount(2, $result);
        $this->assertEquals(0, $result[10]['count']);
        $this->assertEquals(0, $result[20]['count']);
    }

    public function test_build_port_alerts_aggregates_severity(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildAlertsByPort');
        $method->setAccessible(true);

        $alerts = [
            ['port_id' => 10, 'severity' => 'warning'],
            ['port_id' => 10, 'severity' => 'critical'],
        ];

        $result = $method->invoke($this->service, $alerts, [10]);

        $this->assertEquals(2, $result[10]['count']);
        $this->assertEquals('critical', $result[10]['severity']);
    }
}
