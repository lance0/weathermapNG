<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use LibreNMS\Plugins\WeathermapNG\RRD\RRDTool;
use LibreNMS\Plugins\WeathermapNG\Services\Logger;

/**
 * Behavioral tests for pure model accessors and parsers that previously
 * had zero coverage (only string-content source checks).
 */
class PureFunctionsTest extends TestCase
{
    // ---------- Node::convertStatusToString (private) ----------

    private function convertStatus($status): string
    {
        $reflection = new \ReflectionMethod(Node::class, 'convertStatusToString');
        $reflection->setAccessible(true);
        $node = (new \ReflectionClass(Node::class))->newInstanceWithoutConstructor();
        return $reflection->invoke($node, $status);
    }

    public function test_status_null_is_unknown(): void
    {
        $this->assertSame('unknown', $this->convertStatus(null));
    }

    public function test_status_numeric_maps_to_up_down(): void
    {
        $this->assertSame('up', $this->convertStatus(1));
        $this->assertSame('down', $this->convertStatus(0));
        $this->assertSame('up', $this->convertStatus('1'));
    }

    public function test_status_string_maps_case_insensitively(): void
    {
        $this->assertSame('up', $this->convertStatus('up'));
        $this->assertSame('up', $this->convertStatus('UP'));
        $this->assertSame('down', $this->convertStatus('down'));
        $this->assertSame('down', $this->convertStatus('anything-else'));
    }

    // ---------- Link::getBandwidthFormattedAttribute ----------

    private function bandwidthFormatted($bps): string
    {
        $reflection = new \ReflectionClass(Link::class);
        $link = $reflection->newInstanceWithoutConstructor();
        $prop = $reflection->getProperty('attributes');
        $prop->setAccessible(true);
        $prop->setValue($link, ['bandwidth_bps' => $bps]);

        return $link->getBandwidthFormattedAttribute();
    }

    public function test_bandwidth_null_and_zero_are_unknown(): void
    {
        $this->assertSame('Unknown', $this->bandwidthFormatted(null));
        $this->assertSame('Unknown', $this->bandwidthFormatted(0));
    }

    public function test_bandwidth_formats_units(): void
    {
        $this->assertSame('500 bps', $this->bandwidthFormatted(500));
        $this->assertSame('1.5 Kbps', $this->bandwidthFormatted(1500));
        $this->assertSame('100 Mbps', $this->bandwidthFormatted(100000000));
        $this->assertSame('10 Gbps', $this->bandwidthFormatted(10000000000));
    }

    // ---------- RRDTool::parseRRDOutput / getMetricIndex / isValidValue ----------

    private function makeRRDTool(): RRDTool
    {
        return new RRDTool();
    }

    private function invokeRRD(string $method, array $args)
    {
        $tool = $this->makeRRDTool();
        $reflection = new \ReflectionMethod(RRDTool::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($tool, ...$args);
    }

    public function test_parse_rrd_output_extracts_metric_and_skips_nan(): void
    {
        $output = "             traffic_out\n" .
                  "1700000000: 1000 2000\n" .
                  "1700000300: nan 2500\n" .
                  "1700000600: U 3000\n";

        $data = $this->invokeRRD('parseRRDOutput', [$output, 0]);

        // Column 0 is traffic_out: 1000 from row 1, nan/U rows skipped.
        $this->assertCount(1, $data);
        $this->assertSame(1700000000, $data[0]['timestamp']);
        $this->assertSame(1000.0, $data[0]['value']);
    }

    public function test_get_metric_index_resolves_aliases(): void
    {
        $headers = ['traffic_out', 'traffic_in'];
        $this->assertSame(1, $this->invokeRRD('getMetricIndex', ['traffic_in', $headers]));
        $this->assertSame(0, $this->invokeRRD('getMetricIndex', ['traffic_out', $headers]));
        // LibreNMS uppercase alias
        $this->assertSame(1, $this->invokeRRD('getMetricIndex', ['INOCTETS', ['OUTOCTETS', 'INOCTETS']]));
        // Unknown metric falls back to index 0
        $this->assertSame(0, $this->invokeRRD('getMetricIndex', ['nope', ['traffic_in']]));
    }

    public function test_is_valid_value_rejects_sentinels(): void
    {
        $this->assertTrue($this->invokeRRD('isValidValue', ['123.45']));
        $this->assertTrue($this->invokeRRD('isValidValue', ['0']));
        $this->assertFalse($this->invokeRRD('isValidValue', [null]));
        $this->assertFalse($this->invokeRRD('isValidValue', ['nan']));
        $this->assertFalse($this->invokeRRD('isValidValue', ['NAN']));
        $this->assertFalse($this->invokeRRD('isValidValue', ['-nan']));
        $this->assertFalse($this->invokeRRD('isValidValue', ['U']));
        $this->assertFalse($this->invokeRRD('isValidValue', ['abc']));
    }

    // ---------- Logger::sanitizeContext (private) ----------

    public function test_sanitize_context_masks_sensitive_keys(): void
    {
        $reflection = new \ReflectionMethod(Logger::class, 'sanitizeContext');
        $reflection->setAccessible(true);

        // Logger has a private constructor (singleton). Bypass it.
        $logger = (new \ReflectionClass(Logger::class))
            ->newInstanceWithoutConstructor();
        // Ensure the masking config branch is active.
        $configProp = (new \ReflectionClass(Logger::class))->getProperty('config');
        $configProp->setAccessible(true);
        $configProp->setValue($logger, ['security' => ['mask_sensitive_data' => true]]);

        $context = [
            'password' => 'hunter2',
            'api_token' => 'tok123',
            'nested' => ['secret_key' => 'abc', 'safe' => 'keep'],
            'username' => 'admin',
        ];

        $sanitized = $reflection->invoke($logger, $context);

        $this->assertSame('***REDACTED***', $sanitized['password']);
        $this->assertSame('***REDACTED***', $sanitized['api_token']);
        $this->assertSame('***REDACTED***', $sanitized['nested']['secret_key']);
        $this->assertSame('keep', $sanitized['nested']['safe']);
        $this->assertSame('admin', $sanitized['username']);
    }
}
