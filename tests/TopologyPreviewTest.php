<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use LibreNMS\Plugins\WeathermapNG\Services\TopologyPreview;
use PHPUnit\Framework\TestCase;

class TopologyPreviewTest extends TestCase
{
    public function test_previews_are_bounded_per_map_without_changing_counts_or_loading_appends(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite required');
        $resolver = Model::getConnectionResolver();
        $capsule = new Capsule(new \Illuminate\Container\Container());
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->bootEloquent();
        $db = $capsule->getConnection();
        try {
            $db->statement('CREATE TABLE wmng_maps (id INTEGER PRIMARY KEY, name TEXT, options TEXT)');
            $db->statement('CREATE TABLE wmng_nodes (id INTEGER PRIMARY KEY, map_id INTEGER, x REAL, y REAL, device_id INTEGER, label TEXT)');
            $db->statement('CREATE TABLE wmng_links (id INTEGER PRIMARY KEY, map_id INTEGER, src_node_id INTEGER, dst_node_id INTEGER, port_id_a INTEGER)');
            foreach ([1,2] as $mapId) {
                $db->table('wmng_maps')->insert(['id' => $mapId, 'name' => 'map-'.$mapId]);
                $first = ($mapId - 1) * 110 + 1;
                for ($index = 0; $index < 110; $index++) {
                    $db->table('wmng_nodes')->insert(['id' => $first + $index, 'map_id' => $mapId, 'x' => $index * 3, 'y' => $index % 7, 'device_id' => 999, 'label' => 'not in DTO']);
                }
                for ($index = 0; $index < 160; $index++) {
                    $db->table('wmng_links')->insert(['map_id' => $mapId, 'src_node_id' => $first, 'dst_node_id' => $first + 1, 'port_id_a' => 999]);
                }
            }
            $db->enableQueryLog();
            $result = TopologyPreview::loadMaps();
            $this->assertCount(3, $db->getQueryLog(), 'one map/count query plus two bounded relation queries');
            foreach ([1,2] as $id) {
                $map = $result['maps']->firstWhere('id', $id);
                $preview = $result['previews'][$id];
                $this->assertSame(110, $map->nodes_count);
                $this->assertSame(160, $map->links_count);
                $this->assertCount(100, $preview['nodes']);
                $this->assertCount(150, $preview['links']);
                $this->assertTrue($preview['capped']);
                $this->assertSame(range(($id - 1) * 110 + 1, ($id - 1) * 110 + 100), array_column($preview['nodes'], 'id'));
                $this->assertSame(['id','x','y'], array_keys($preview['nodes'][0]));
                $this->assertSame(['src','dst'], array_keys($preview['links'][0]));
                $this->assertIsFloat($preview['nodes'][0]['x']);
                $this->assertSame(['id','map_id','x','y'], array_keys($map->nodes->first()->getAttributes()));
            }
            // More maps do not add queries, and empty maps keep truthful counts/placeholders.
            $db->table('wmng_maps')->insert(['id' => 3, 'name' => 'empty']);
            $db->flushQueryLog();
            $result = TopologyPreview::loadMaps();
            $this->assertCount(3, $db->getQueryLog());
            $this->assertSame(['nodes' => [], 'links' => [], 'capped' => false], $result['previews'][3]);
            $this->assertSame(0, $result['maps']->firstWhere('id', 3)->nodes_count);
            // Only the three expected queries are allowed; no device/port tables are provisioned.
        } finally {
            $db->disconnect();
            if ($resolver) Model::setConnectionResolver($resolver);
            else Model::unsetConnectionResolver();
        }
    }

    public function test_svg_renderer_executes_edge_case_checks(): void
    {
        $node = (new \Symfony\Component\Process\ExecutableFinder())->find('node');
        if (!$node) $this->markTestSkipped('Node renderer assertions also run in browser CI');
        $process = new \Symfony\Component\Process\Process([$node, __DIR__.'/topology-preview-test.cjs']);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
    }
}
