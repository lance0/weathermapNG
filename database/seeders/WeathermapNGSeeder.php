<?php

namespace LibreNMS\Plugins\WeathermapNG\Database\Seeders;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;
use Illuminate\Database\Seeder;

class WeathermapNGSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a demo map
        $map = Map::create([
            'name' => 'demo_network',
            'title' => 'Demo Network Map',
            'options' => [
                'width' => 800,
                'height' => 600,
                'background' => '#f8f9fa',
                'description' => 'Sample network topology for demonstration'
            ]
        ]);

        // Create demo nodes (using placeholder device IDs)
        $coreRouter = Node::create([
            'map_id' => $map->id,
            'label' => 'Core Router',
            'x' => 400,
            'y' => 100,
            'device_id' => 1, // Replace with actual device ID
            'meta' => [
                'type' => 'router',
                'vendor' => 'Cisco',
                'model' => 'ISR 4451'
            ]
        ]);

        $switch1 = Node::create([
            'map_id' => $map->id,
            'label' => 'Access Switch 1',
            'x' => 200,
            'y' => 300,
            'device_id' => 2, // Replace with actual device ID
            'meta' => [
                'type' => 'switch',
                'vendor' => 'Cisco',
                'model' => 'Catalyst 2960'
            ]
        ]);

        $switch2 = Node::create([
            'map_id' => $map->id,
            'label' => 'Access Switch 2',
            'x' => 600,
            'y' => 300,
            'device_id' => 3, // Replace with actual device ID
            'meta' => [
                'type' => 'switch',
                'vendor' => 'Cisco',
                'model' => 'Catalyst 2960'
            ]
        ]);

        $server = Node::create([
            'map_id' => $map->id,
            'label' => 'File Server',
            'x' => 400,
            'y' => 450,
            'device_id' => 4, // Replace with actual device ID
            'meta' => [
                'type' => 'server',
                'os' => 'Ubuntu Server',
                'role' => 'File Storage'
            ]
        ]);

        // Create demo links
        Link::create([
            'map_id' => $map->id,
            'src_node_id' => $coreRouter->id,
            'dst_node_id' => $switch1->id,
            'port_id_a' => 1, // Replace with actual port IDs
            'port_id_b' => 1,
            'bandwidth_bps' => 1000000000, // 1Gbps
            'style' => [
                'color' => '#28a745',
                'width' => 3,
                'label' => '1Gbps Uplink'
            ]
        ]);

        Link::create([
            'map_id' => $map->id,
            'src_node_id' => $coreRouter->id,
            'dst_node_id' => $switch2->id,
            'port_id_a' => 2, // Replace with actual port IDs
            'port_id_b' => 1,
            'bandwidth_bps' => 1000000000, // 1Gbps
            'style' => [
                'color' => '#28a745',
                'width' => 3,
                'label' => '1Gbps Uplink'
            ]
        ]);

        Link::create([
            'map_id' => $map->id,
            'src_node_id' => $switch1->id,
            'dst_node_id' => $server->id,
            'port_id_a' => 2, // Replace with actual port IDs
            'port_id_b' => 1,
            'bandwidth_bps' => 100000000, // 100Mbps
            'style' => [
                'color' => '#17a2b8',
                'width' => 2,
                'label' => '100Mbps'
            ]
        ]);

        Link::create([
            'map_id' => $map->id,
            'src_node_id' => $switch2->id,
            'dst_node_id' => $server->id,
            'port_id_a' => 2, // Replace with actual port IDs
            'port_id_b' => 2,
            'bandwidth_bps' => 100000000, // 100Mbps
            'style' => [
                'color' => '#17a2b8',
                'width' => 2,
                'label' => '100Mbps'
            ]
        ]);

        $this->command->info('Demo network map created successfully!');
        $this->command->info('Map ID: ' . $map->id);
        $this->command->info('You can now view it at: /plugins/weathermapng/maps/' . $map->id);
    }
}