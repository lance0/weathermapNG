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
        $ref->setAccessible(true);
        $row = $ref->invoke($this->rrdTool, $output);

        $this->assertIsArray($row);
        $this->assertEquals('1500', $row['traffic_in']);
        $this->assertEquals('2500', $row['traffic_out']);
    }

    // Note: Full RRD testing requires actual RRD files and rrdtool binary
    // These basic tests verify the class structure and error handling
    // Comprehensive testing would require test RRD files and rrdtool installation
}