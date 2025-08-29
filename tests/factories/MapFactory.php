<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Factories;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use Faker\Generator as Faker;

class MapFactory
{
    public static function create(array $attributes = []): Map
    {
        $faker = app(Faker::class);

        return Map::create(array_merge([
            'name' => 'test_map_' . $faker->unique()->slug(2),
            'title' => $faker->sentence(3),
            'options' => [
                'width' => $faker->numberBetween(400, 1200),
                'height' => $faker->numberBetween(300, 800),
                'background' => $faker->hexColor(),
                'description' => $faker->optional()->paragraph()
            ]
        ], $attributes));
    }

    public static function createWithNodes(int $nodeCount = 3, array $mapAttributes = []): Map
    {
        $map = self::create($mapAttributes);

        for ($i = 0; $i < $nodeCount; $i++) {
            NodeFactory::create(['map_id' => $map->id]);
        }

        return $map;
    }

    public static function createWithNodesAndLinks(int $nodeCount = 3, int $linkCount = 2, array $mapAttributes = []): Map
    {
        $map = self::createWithNodes($nodeCount, $mapAttributes);

        $nodes = $map->nodes()->get();
        $createdLinks = 0;

        for ($i = 0; $i < $nodes->count() - 1 && $createdLinks < $linkCount; $i++) {
            LinkFactory::create([
                'map_id' => $map->id,
                'src_node_id' => $nodes[$i]->id,
                'dst_node_id' => $nodes[$i + 1]->id
            ]);
            $createdLinks++;
        }

        return $map;
    }
}