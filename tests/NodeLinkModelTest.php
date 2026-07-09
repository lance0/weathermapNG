<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class NodeLinkModelTest extends TestCase
{
    public function test_node_casts_numeric_columns(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Models/Node.php');
        $this->assertStringContainsString("'device_id' => 'integer'", $content);
        $this->assertStringContainsString("'x' => 'float'", $content);
        $this->assertStringContainsString("'y' => 'float'", $content);
    }

    public function test_link_casts_numeric_columns(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Models/Link.php');
        $this->assertStringContainsString("'src_node_id' => 'integer'", $content);
        $this->assertStringContainsString("'dst_node_id' => 'integer'", $content);
        $this->assertStringContainsString("'port_id_a' => 'integer'", $content);
        $this->assertStringContainsString("'port_id_b' => 'integer'", $content);
        $this->assertStringContainsString("'bandwidth_bps' => 'integer'", $content);
    }
}
