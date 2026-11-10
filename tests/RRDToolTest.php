<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\RRD\RRDTool;
use PHPUnit\Framework\TestCase;

class RRDToolTest extends TestCase
{
    protected RRDTool $rrdTool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rrdTool = new RRDTool();
    }

    /** @test */
    public function rrdtool_can_be_instantiated()
    {
        $this->assertInstanceOf(RRDTool::class, $this->rrdTool);
    }

    /** @test */
    public function rrdtool_has_required_methods()
    {
        $this->assertTrue(method_exists($this->rrdTool, 'fetch'));
        $this->assertTrue(method_exists($this->rrdTool, 'getLastValue'));
        $this->assertTrue(method_exists($this->rrdTool, 'getAverageValue'));
        $this->assertTrue(method_exists($this->rrdTool, 'getLastValues'));
    }

    /** @test */
    public function fetch_returns_empty_array_for_nonexistent_file()
    {
        $result = $this->rrdTool->fetch('/nonexistent/file.rrd', 'traffic_in');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function getLastValue_returns_null_for_nonexistent_file()
    {
        $result = $this->rrdTool->getLastValue('/nonexistent/file.rrd', 'traffic_in');
        $this->assertNull($result);
    }

    /** @test */
    public function getAverageValue_returns_null_for_nonexistent_file()
    {
        $result = $this->rrdTool->getAverageValue('/nonexistent/file.rrd', 'traffic_in');
        $this->assertNull($result);
    }

    /** @test */
    public function getLastValues_returns_empty_array_for_nonexistent_file()
    {
        $result = $this->rrdTool->getLastValues('/nonexistent/file.rrd');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function getLastValues_returns_both_traffic_metrics_from_single_invocation()
    {
        // Simulate rrdtool fetch output with both traffic_in and traffic_out columns.
        // Uses Reflection to test the private parseLastRow parser directly.
        $output = "traffic_in traffic_out\n" .
                  "1700000000: 1000 2000\n" .
                  "1700000300: 1500 2500\n";

        $ref = new \ReflectionMethod($this->rrdTool, 'parseLastRow');
        $row = $ref->invoke($this->rrdTool, $output);

        $this->assertIsArray($row);
        $this->assertEquals('1500', $row['traffic_in']);
        $this->assertEquals('2500', $row['traffic_out']);
    }

    // Note: Full RRD testing requires actual RRD files and rrdtool binary
    // These basic tests verify the class structure and error handling
    // Comprehensive testing would require test RRD files and rrdtool installation

    private function parseOutput(string $output, string $metric): array
    {
        $method = (new \ReflectionClass($this->rrdTool))->getMethod('parseRRDOutput');

        return $method->invoke($this->rrdTool, $output, $metric);
    }

    /** @test */
    public function parses_values_when_header_includes_timestamp_column()
    {
        $output = <<<'RRD'
            timestamp traffic_in traffic_out
            1750000000: 1000 2000
            1750000060: 1500 2500
            RRD;

        $this->assertSame([
            ['timestamp' => 1750000000, 'value' => 1000.0],
            ['timestamp' => 1750000060, 'value' => 1500.0],
        ], $this->parseOutput($output, 'traffic_in'));
    }

    /** @test */
    public function skips_unknown_values_when_header_includes_timestamp_column()
    {
        $output = <<<'RRD'
            timestamp traffic_in
            1750000000: 1000
            1750000060: U
            1750000120: nan
            1750000180: 1700
            RRD;

        $values = $this->parseOutput($output, 'traffic_in');

        $this->assertCount(2, $values);
        $this->assertSame(1000.0, $values[0]['value']);
        $this->assertSame(1700.0, $values[1]['value']);
    }

    /** @test */
    public function last_row_parser_aligns_values_without_timestamp_column()
    {
        $output = <<<'RRD'
            timestamp traffic_in traffic_out
            1750000000: 1000 2000
            1750000060: 1500 2500
            RRD;

        $method = (new \ReflectionClass($this->rrdTool))->getMethod('parseLastRow');
        $row = $method->invoke($this->rrdTool, $output);

        $this->assertSame('1500', $row['traffic_in']);
        $this->assertSame('2500', $row['traffic_out']);
    }
}
