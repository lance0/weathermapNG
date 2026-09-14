<?php

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\LegacyConfService;
use LibreNMS\Plugins\WeathermapNG\Models\Map;

class LegacyConfServiceTest extends TestCase
{
    public function test_parses_example_conf()
    {
        $content = file_get_contents(__DIR__ . '/../config/maps/example.conf');
        $result = (new LegacyConfService())->parse($content, 'example');

        $this->assertSame(
            ['width' => 800, 'height' => 600, 'background' => '#ffffff'],
            $result['options']
        );
        $this->assertCount(4, $result['nodes']);
        $this->assertCount(3, $result['links']);

        $first = $result['nodes'][0];
        $this->assertSame('Core Router 1', $first['label']);
        $this->assertSame(200.0, $first['x']);
        $this->assertSame(150.0, $first['y']);
        $this->assertSame(1, $first['device_id']);
        $this->assertSame(1, $first['meta']['interface_id']);
        $this->assertSame('traffic_in', $first['meta']['metric']);

        // Links reference node positions from the same file.
        $this->assertSame(0, $result['links'][0]['src']);
        $this->assertSame(1, $result['links'][0]['dst']);
        $this->assertSame(1000000000, $result['links'][0]['bandwidth_bps']);
        $this->assertSame(['label' => '1Gbps Backbone'], $result['links'][0]['style']);
    }

    public function test_rejects_directive_outside_section()
    {
        $this->expectException(InvalidArgumentException::class);

        (new LegacyConfService())->parse("foo bar\n", 'x');
    }

    public function test_rejects_duplicate_node_section()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate node section');

        (new LegacyConfService())->parse("[node:a]\n[node:a]\n", 'x');
    }


    public function test_link_with_unknown_node_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no usable `nodes` line');

        (new LegacyConfService())->parse(
            "[node:a]\nlabel \"A\"\n\n[link:broken]\nnodes a unknown\n",
            'x'
        );
    }
    public function test_clamps_extreme_dimensions()
    {
        $result = (new LegacyConfService())->parse("[global]\nwidth 999999\nheight 0\n", 'x');
        $this->assertSame(4096, $result['options']['width']);
        $this->assertSame(100, $result['options']['height']);
    }

    public function test_defaults_when_global_missing()
    {
        $result = (new LegacyConfService())->parse("[node:a]\nlabel \"A\"\n", 'emptiness');

        $this->assertSame(
            ['width' => 800, 'height' => 600, 'background' => '#ffffff'],
            $result['options']
        );
        $this->assertCount(1, $result['nodes']);
        $this->assertSame('A', $result['nodes'][0]['label']);
        // Fallback position for nodes without x/y — 100px apart, inside default canvas.
        $this->assertIsFloat($result['nodes'][0]['x']);
        $this->assertSame(100.0, $result['nodes'][0]['x']);
        $this->assertIsFloat($result['nodes'][0]['y']);
        $this->assertSame(100.0, $result['nodes'][0]['y']);
    }

    public function test_skips_comments_and_blank_lines()
    {
        $conf = "# top comment\n; also comment\n\n[node:a]\nlabel \"A\"\n\n[link:a-a]\nnodes a a\n";
        $result = (new LegacyConfService())->parse($conf, 'x');

        $this->assertCount(1, $result['nodes']);
        $this->assertCount(1, $result['links']);
    }

    public function test_quotes_are_stripped_from_labels()
    {
        $result = (new LegacyConfService())->parse("[node:sw]\nlabel \"My Switch\"\n", 'x');

        $this->assertSame('My Switch', $result['nodes'][0]['label']);
    }

    public function test_hello_confExport_roundtrip()
    {
        $this->markTestSkipped('Requires database connection for model relationships');
    }
}
