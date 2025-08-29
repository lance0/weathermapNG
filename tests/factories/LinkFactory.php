<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Factories;

use LibreNMS\Plugins\WeathermapNG\Models\Link;
use Faker\Generator as Faker;

class LinkFactory
{
    public static function create(array $attributes = []): Link
    {
        $faker = app(Faker::class);

        return Link::create(array_merge([
            'map_id' => $attributes['map_id'] ?? 1,
            'src_node_id' => $attributes['src_node_id'] ?? 1,
            'dst_node_id' => $attributes['dst_node_id'] ?? 2,
            'port_id_a' => $faker->optional()->numberBetween(1, 1000),
            'port_id_b' => $faker->optional()->numberBetween(1, 1000),
            'bandwidth_bps' => $faker->randomElement([
                1000000,      // 1 Mbps
                10000000,     // 10 Mbps
                100000000,    // 100 Mbps
                1000000000,   // 1 Gbps
                10000000000   // 10 Gbps
            ]),
            'style' => [
                'color' => $faker->hexColor(),
                'width' => $faker->numberBetween(1, 5),
                'label' => $faker->optional()->word()
            ]
        ], $attributes));
    }

    public static function createHighSpeedLink(array $attributes = []): Link
    {
        return self::create(array_merge([
            'bandwidth_bps' => 10000000000, // 10 Gbps
            'style' => [
                'color' => '#28a745',
                'width' => 4,
                'label' => '10Gbps Backbone'
            ]
        ], $attributes));
    }

    public static function createMediumSpeedLink(array $attributes = []): Link
    {
        return self::create(array_merge([
            'bandwidth_bps' => 1000000000, // 1 Gbps
            'style' => [
                'color' => '#17a2b8',
                'width' => 3,
                'label' => '1Gbps Uplink'
            ]
        ], $attributes));
    }

    public static function createLowSpeedLink(array $attributes = []): Link
    {
        return self::create(array_merge([
            'bandwidth_bps' => 100000000, // 100 Mbps
            'style' => [
                'color' => '#ffc107',
                'width' => 2,
                'label' => '100Mbps'
            ]
        ], $attributes));
    }
}