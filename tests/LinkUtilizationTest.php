<?php

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Node;
use LibreNMS\Plugins\WeathermapNG\Link;

class LinkUtilizationTest extends TestCase
{
    private function nodeWithSeries(string $id, array $values): Node
    {
        $n = new Node($id, [
            'label' => $id,
            'x' => 0,
            'y' => 0,
        ]);
        $series = [];
        $t = time() - count($values) * 60;
        foreach ($values as $v) {
            $series[] = ['timestamp' => $t, 'value' => $v];
            $t += 60;
        }
        $n->setData($series);
        $n->setStatus('up');
        return $n;
    }

    public function test_link_utilization_uses_max_direction()
    {
        $a = $this->nodeWithSeries('A', [10, 20, 30]);
        $b = $this->nodeWithSeries('B', [5, 15, 100]);

        $link = new Link('A-B', $a, $b, ['bandwidth' => 1000]);
        $link->calculateUtilization();

        // Highest current value among end nodes is 100; utilization should be 0.1
        $this->assertEquals(0.1, $link->getUtilization());

        // Status should not be critical/warning at 10%
        $this->assertSame('normal', $link->getStatus());
        $this->assertGreaterThan(0, $link->getWidth());
    }

    public function test_link_status_thresholds()
    {
        $a = $this->nodeWithSeries('A', [900]);
        $b = $this->nodeWithSeries('B', [0]);
        $link = new Link('A-B', $a, $b, ['bandwidth' => 1000]);
        $link->calculateUtilization();
        $this->assertSame('warning', $link->getStatus()); // 90% -> warning

        $a2 = $this->nodeWithSeries('A', [950]);
        $b2 = $this->nodeWithSeries('B', [0]);
        $link2 = new Link('A2-B2', $a2, $b2, ['bandwidth' => 1000]);
        $link2->calculateUtilization();
        $this->assertSame('critical', $link2->getStatus()); // 95% -> critical
    }
}

