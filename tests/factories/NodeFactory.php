<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Factories;

use LibreNMS\Plugins\WeathermapNG\Models\Node;
use Faker\Generator as Faker;

class NodeFactory
{
    public static function create(array $attributes = []): Node
    {
        $faker = app(Faker::class);

        return Node::create(array_merge([
            'map_id' => $attributes['map_id'] ?? 1,
            'label' => $faker->words(2, true),
            'x' => $faker->numberBetween(0, 800),
            'y' => $faker->numberBetween(0, 600),
            'device_id' => $faker->optional()->numberBetween(1, 100),
            'meta' => [
                'type' => $faker->randomElement(['router', 'switch', 'server', 'firewall']),
                'vendor' => $faker->randomElement(['Cisco', 'Juniper', 'HP', 'Dell']),
                'model' => $faker->word()
            ]
        ], $attributes));
    }

    public static function createRouter(array $attributes = []): Node
    {
        return self::create(array_merge([
            'label' => 'Core Router',
            'meta' => [
                'type' => 'router',
                'vendor' => 'Cisco',
                'model' => 'ISR 4451'
            ]
        ], $attributes));
    }

    public static function createSwitch(array $attributes = []): Node
    {
        return self::create(array_merge([
            'label' => 'Access Switch',
            'meta' => [
                'type' => 'switch',
                'vendor' => 'Cisco',
                'model' => 'Catalyst 2960'
            ]
        ], $attributes));
    }

    public static function createServer(array $attributes = []): Node
    {
        return self::create(array_merge([
            'label' => 'File Server',
            'meta' => [
                'type' => 'server',
                'vendor' => 'Dell',
                'model' => 'PowerEdge R740'
            ]
        ], $attributes));
    }
}