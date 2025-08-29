<?php

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Node;

class NodeDataTest extends TestCase
{
    public function test_current_avg_max_values()
    {
        $n = new Node('n1', ['label' => 'n1']);
        $now = time();
        $series = [
            ['timestamp' => $now - 300, 'value' => 0],
            ['timestamp' => $now - 200, 'value' => 10],
            ['timestamp' => $now - 100, 'value' => 20],
            ['timestamp' => $now, 'value' => 30],
        ];
        $n->setData($series);

        $this->assertSame(30, $n->getCurrentValue());
        $this->assertSame(30, $n->getMaxValue());
        $this->assertEquals(20, $n->getAverageValue()); // (10+20+30)/3 - ignores zero in code
    }
}
