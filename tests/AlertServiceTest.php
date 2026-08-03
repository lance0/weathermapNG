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
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('db');
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('db');
        parent::tearDown();
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

    // --- deviceAlertHistory / portAlertHistory tests ---

    /**
     * Register a fake DB facade accessor in the container so the service's
     * DB::table(...) chain returns canned rows. Returns the fake builder so
     * individual tests can prime it.
     */
    private function &fakeDb(): object
    {
        $app = \Illuminate\Support\Facades\Facade::getFacadeApplication();

        $builder = new class {
            private $rows = [];
            public array $selectedColumns = [];
            public array $wheres = [];
            public ?string $orderBy = null;
            public ?int $limit = null;
            public string $table = '';

            public function setRows(array $rows): void { $this->rows = $rows; }
            public function select(...$cols): static { $this->selectedColumns = array_merge($this->selectedColumns, $cols); return $this; }
            public function where($col, $op = null, $val = null): static { $this->wheres[] = [$col, $op, $val]; return $this; }
            public function whereIn($col, $vals): static { $this->wheres[] = [$col, 'in', $vals]; return $this; }
            public function whereNotIn($col, $vals): static { return $this; }
            public function orderBy($col, $dir = 'asc'): static { $this->orderBy = $col . ':' . $dir; return $this; }
            public function limit($n): static { $this->limit = $n; return $this; }
            public function get()
            {
                $rows = $this->rows;
                if ($this->orderBy === 'timestamp:desc') {
                    usort($rows, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
                }
                if ($this->limit !== null) {
                    $rows = array_slice($rows, 0, $this->limit);
                }
                return collect($rows);
            }
        };

        $fakeConnection = new class($builder) {
            private $builder;
            public function __construct($b) { $this->builder = $b; }
            public function table($table)
            {
                $this->builder->table = $table;
                return $this->builder;
            }
        };

        $app->instance('db', $fakeConnection);
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('db');

        return $builder;
    }

    /**
     * Force the DB facade to throw on table() so we exercise the catch path.
     */
    private function breakDb(): void
    {
        $app = \Illuminate\Support\Facades\Facade::getFacadeApplication();
        $app->instance('db', new class {
            public function table($t) { throw new \RuntimeException('db down'); }
        });
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('db');
    }

    public function test_device_alert_history_returns_empty_for_empty_input(): void
    {
        $this->assertEmpty($this->service->deviceAlertHistory([]));
    }

    public function test_device_alert_history_includes_all_states(): void
    {
        $builder = $this->fakeDb();
        $builder->setRows([
            ['id' => 1, 'device_id' => 5, 'state' => 0, 'severity' => 'ok', 'timestamp' => '2026-01-01'],
            ['id' => 2, 'device_id' => 5, 'state' => 2, 'severity' => 'critical', 'timestamp' => '2026-02-01'],
            ['id' => 3, 'device_id' => 5, 'state' => 1, 'severity' => 'warning', 'timestamp' => '2026-03-01'],
        ]);

        $result = $this->service->deviceAlertHistory([5]);

        $states = array_column($result, 'state');
        $this->assertContains(0, $states, 'resolved alerts (state 0) must be included');
        $this->assertContains(2, $states, 'acknowledged alerts (state 2) must be included');
        $this->assertContains(1, $states, 'active alerts (state 1) must be included');
    }

    public function test_device_alert_history_ordered_by_timestamp_desc(): void
    {
        $builder = $this->fakeDb();
        $builder->setRows([
            ['id' => 1, 'device_id' => 5, 'state' => 0, 'severity' => 'ok', 'timestamp' => '2026-01-01 00:00:00'],
            ['id' => 3, 'device_id' => 5, 'state' => 1, 'severity' => 'warning', 'timestamp' => '2026-03-01 00:00:00'],
            ['id' => 2, 'device_id' => 5, 'state' => 2, 'severity' => 'critical', 'timestamp' => '2026-02-01 00:00:00'],
        ]);

        $result = $this->service->deviceAlertHistory([5]);

        $ids = array_column($result, 'id');
        $this->assertEquals([3, 2, 1], $ids, 'results must be ordered by timestamp DESC');
    }

    public function test_device_alert_history_respects_limit(): void
    {
        $builder = $this->fakeDb();
        $rows = [];
        for ($i = 0; $i < 15; $i++) {
            $rows[] = ['id' => $i + 1, 'device_id' => 5, 'state' => 1, 'severity' => 'warning', 'timestamp' => sprintf('2026-01-%02d', $i + 1)];
        }
        $builder->setRows($rows);

        $result = $this->service->deviceAlertHistory([5], 5);

        $this->assertCount(5, $result);
        $this->assertEquals(5, $builder->limit, 'limit must be forwarded to the query');
    }

    public function test_device_alert_history_selects_required_columns(): void
    {
        $builder = $this->fakeDb();
        $builder->setRows([
            ['id' => 1, 'device_id' => 5, 'state' => 0, 'severity' => 'ok', 'timestamp' => '2026-01-01'],
        ]);

        $this->service->deviceAlertHistory([5]);

        $this->assertEquals(['id', 'device_id', 'state', 'severity', 'timestamp'], $builder->selectedColumns);
    }

    public function test_device_alert_history_no_state_filter(): void
    {
        $builder = $this->fakeDb();
        $builder->setRows([
            ['id' => 1, 'device_id' => 5, 'state' => 0, 'severity' => 'ok', 'timestamp' => '2026-01-01'],
        ]);

        $this->service->deviceAlertHistory([5]);

        $this->assertNotEmpty($builder->wheres, 'query must contain where clauses (device filter)');
        foreach ($builder->wheres as $where) {
            $this->assertNotEquals('state', $where[0], 'history query must NOT filter by state');
        }
    }

    public function test_device_alert_history_returns_empty_on_failure(): void
    {
        $this->breakDb();
        $this->assertSame([], $this->service->deviceAlertHistory([5]));
    }

    public function test_port_alert_history_returns_empty_for_empty_input(): void
    {
        $this->assertEmpty($this->service->portAlertHistory([]));
    }

    public function test_port_alert_history_includes_all_states(): void
    {
        $builder = $this->fakeDb();
        $builder->setRows([
            ['id' => 10, 'port_id' => 100, 'state' => 0, 'severity' => 'ok', 'timestamp' => '2026-01-01'],
            ['id' => 11, 'port_id' => 100, 'state' => 2, 'severity' => 'critical', 'timestamp' => '2026-02-01'],
            ['id' => 12, 'port_id' => 100, 'state' => 1, 'severity' => 'warning', 'timestamp' => '2026-03-01'],
        ]);

        $result = $this->service->portAlertHistory([100]);

        $states = array_column($result, 'state');
        $this->assertContains(0, $states, 'resolved alerts (state 0) must be included');
        $this->assertContains(2, $states, 'acknowledged alerts (state 2) must be included');
        $this->assertContains(1, $states, 'active alerts (state 1) must be included');
    }

    public function test_port_alert_history_ordered_by_timestamp_desc(): void
    {
        $builder = $this->fakeDb();
        $builder->setRows([
            ['id' => 10, 'port_id' => 100, 'state' => 0, 'severity' => 'ok', 'timestamp' => '2026-01-01 00:00:00'],
            ['id' => 12, 'port_id' => 100, 'state' => 1, 'severity' => 'warning', 'timestamp' => '2026-03-01 00:00:00'],
            ['id' => 11, 'port_id' => 100, 'state' => 2, 'severity' => 'critical', 'timestamp' => '2026-02-01 00:00:00'],
        ]);

        $result = $this->service->portAlertHistory([100]);

        $ids = array_column($result, 'id');
        $this->assertEquals([12, 11, 10], $ids, 'results must be ordered by timestamp DESC');
    }

    public function test_port_alert_history_respects_limit(): void
    {
        $builder = $this->fakeDb();
        $rows = [];
        for ($i = 0; $i < 15; $i++) {
            $rows[] = ['id' => $i + 1, 'port_id' => 100, 'state' => 1, 'severity' => 'warning', 'timestamp' => sprintf('2026-01-%02d', $i + 1)];
        }
        $builder->setRows($rows);

        $result = $this->service->portAlertHistory([100], 5);

        $this->assertCount(5, $result);
        $this->assertEquals(5, $builder->limit, 'limit must be forwarded to the query');
    }

    public function test_port_alert_history_selects_entity_id_as_port_id(): void
    {
        $builder = $this->fakeDb();
        $builder->setRows([
            ['id' => 10, 'port_id' => 100, 'state' => 0, 'severity' => 'ok', 'timestamp' => '2026-01-01'],
        ]);

        $this->service->portAlertHistory([100]);

        $this->assertEquals(['id', 'entity_id as port_id', 'state', 'severity', 'timestamp'], $builder->selectedColumns);
        $hasEntityType = false;
        foreach ($builder->wheres as $where) {
            if ($where[0] === 'entity_type' && $where[1] === 'port') {
                $hasEntityType = true;
            }
        }
        $this->assertTrue($hasEntityType, 'port history must filter entity_type = port');
    }

    public function test_port_alert_history_no_state_filter(): void
    {
        $builder = $this->fakeDb();
        $builder->setRows([
            ['id' => 10, 'port_id' => 100, 'state' => 0, 'severity' => 'ok', 'timestamp' => '2026-01-01'],
        ]);

        $this->service->portAlertHistory([100]);
        $this->assertNotEmpty($builder->wheres, 'query must contain where clauses (port filter)');
        foreach ($builder->wheres as $where) {
            $this->assertNotEquals('state', $where[0], 'port history query must NOT filter by state');
        }
    }

    public function test_port_alert_history_returns_empty_on_failure(): void
    {
        $this->breakDb();
        $this->assertSame([], $this->service->portAlertHistory([100]));
    }
}
