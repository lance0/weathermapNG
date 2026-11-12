<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\Services\AlertService;
use LibreNMS\Plugins\WeathermapNG\Services\LinkDataService;
use PHPUnit\Framework\TestCase;

/**
 * Link alert payload shape.
 *
 * The embed viewer reads `link.alerts.count` and `link.alerts.severity`, the
 * same shape node alerts use. The service previously emitted
 * `alert_count`/`alert_severity`, so link alert badges never rendered even
 * though the data was present.
 */
class LinkAlertPayloadTest extends TestCase
{
    private function calculate(array $portAlerts, ?int $portA = 101, ?int $portB = 102): array
    {
        $service = new LinkDataService(new AlertService());
        $method = new \ReflectionMethod($service, 'calculateLinkAlertInfo');
        $link = new Link(['port_id_a' => $portA, 'port_id_b' => $portB]);

        return $method->invoke($service, $link, $portAlerts);
    }

    public function test_emits_the_shape_the_embed_reads(): void
    {
        $alerts = $this->calculate([
            101 => ['count' => 2, 'severity' => 'warning'],
            102 => ['count' => 1, 'severity' => 'critical'],
        ]);

        $this->assertSame(['count' => 3, 'severity' => 'critical'], $alerts);
        $this->assertArrayNotHasKey('alert_count', $alerts);
        $this->assertArrayNotHasKey('alert_severity', $alerts);
    }

    public function test_returns_empty_when_no_port_has_alerts(): void
    {
        $this->assertSame([], $this->calculate([], null, null));
        $this->assertSame([], $this->calculate([101 => ['count' => 0, 'severity' => 'ok']]));
    }

    public function test_missing_severity_falls_back_to_warning(): void
    {
        $alerts = $this->calculate([101 => ['count' => 1, 'severity' => '']]);
        $this->assertSame(1, $alerts['count']);
        $this->assertSame('warning', $alerts['severity']);
    }
}
