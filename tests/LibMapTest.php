<?php

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Map;
use LibreNMS\Plugins\WeathermapNG\Node;
use LibreNMS\Plugins\WeathermapNG\Link;

class LibMapTest extends TestCase
{
    private function exampleConfig(): string
    {
        return __DIR__ . '/../config/maps/example.conf';
    }

    public function test_parses_example_config()
    {
        $map = new Map($this->exampleConfig());

        $this->assertSame('Example Network Map', $map->getTitle());
        $this->assertSame(800, $map->getWidth());
        $this->assertSame(600, $map->getHeight());

        $nodes = $map->getNodes();
        $links = $map->getLinks();

        $this->assertNotEmpty($nodes);
        $this->assertNotEmpty($links);

        $this->assertArrayHasKey('router1', $nodes);
        $this->assertArrayHasKey('router2', $nodes);
        $this->assertArrayHasKey('switch1', $nodes);

        $router1 = $map->getNode('router1');
        $this->assertInstanceOf(Node::class, $router1);
        $this->assertSame('Core Router 1', $router1->getLabel());
        $this->assertSame(['x' => 200, 'y' => 150], $router1->getPosition());

        $link = $map->getLink('router1-router2');
        $this->assertInstanceOf(Link::class, $link);
        $this->assertSame('router1', $link->getSourceId());
        $this->assertSame('router2', $link->getTargetId());
        $this->assertSame(1000000000, $link->getBandwidth());
    }

    public function test_to_array_structure_is_reasonable()
    {
        $map = new Map($this->exampleConfig());
        $arr = $map->toArray();

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('title', $arr);
        $this->assertArrayHasKey('width', $arr);
        $this->assertArrayHasKey('height', $arr);
        $this->assertArrayHasKey('nodes', $arr);
        $this->assertArrayHasKey('links', $arr);
        $this->assertArrayHasKey('metadata', $arr);

        $this->assertGreaterThan(0, $arr['metadata']['total_nodes']);
        $this->assertGreaterThan(0, $arr['metadata']['total_links']);
    }
}

