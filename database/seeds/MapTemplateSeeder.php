<?php

use Illuminate\Support\Facades\DB;

class MapTemplateSeeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'small-network',
                'title' => 'Small Network',
                'description' => 'Simple 2-router network with direct connection',
                'width' => 800,
                'height' => 600,
                'category' => 'basic',
                'icon' => 'fas fa-network-wired',
                'is_built_in' => true,
                'config' => json_encode([
                    'default_nodes' => [
                        ['label' => 'Router A', 'x' => 150, 'y' => 300],
                        ['label' => 'Router B', 'x' => 650, 'y' => 300],
                    ],
                    'default_links' => [
                        ['src_node_idx' => 0, 'dst_node_idx' => 1],
                    ],
                ]),
            ],
            [
                'name' => 'star-topology',
                'title' => 'Star Topology',
                'description' => 'Star network topology with central router',
                'width' => 1000,
                'height' => 700,
                'category' => 'basic',
                'icon' => 'fas fa-project-diagram',
                'is_built_in' => true,
                'config' => json_encode([
                    'default_nodes' => [
                        ['label' => 'Core Router', 'x' => 500, 'y' => 350],
                        ['label' => 'Edge Router 1', 'x' => 250, 'y' => 150],
                        ['label' => 'Edge Router 2', 'x' => 750, 'y' => 150],
                        ['label' => 'Edge Router 3', 'x' => 500, 'y' => 550],
                    ],
                    'default_links' => [
                        ['src_node_idx' => 0, 'dst_node_idx' => 1],
                        ['src_node_idx' => 0, 'dst_node_idx' => 2],
                        ['src_node_idx' => 0, 'dst_node_idx' => 3],
                    ],
                ]),
            ],
            [
                'name' => 'redundant-links',
                'title' => 'Redundant Links',
                'description' => 'Dual-homed network with redundant paths',
                'width' => 1000,
                'height' => 800,
                'category' => 'advanced',
                'icon' => 'fas fa-server',
                'is_built_in' => true,
                'config' => json_encode([
                    'default_nodes' => [
                        ['label' => 'Site A Router 1', 'x' => 200, 'y' => 300],
                        ['label' => 'Site A Router 2', 'x' => 800, 'y' => 300],
                        ['label' => 'Site B Router 1', 'x' => 200, 'y' => 500],
                        ['label' => 'Site B Router 2', 'x' => 800, 'y' => 500],
                    ],
                    'default_links' => [
                        ['src_node_idx' => 0, 'dst_node_idx' => 2],
                        ['src_node_idx' => 1, 'dst_node_idx' => 3],
                        ['src_node_idx' => 2, 'dst_node_idx' => 3],
                        ['src_node_idx' => 0, 'dst_node_idx' => 1],
                    ],
                ]),
            ],
            [
                'name' => 'isp-backbone',
                'title' => 'ISP Backbone',
                'description' => 'Multi-tier ISP backbone network',
                'width' => 1400,
                'height' => 900,
                'category' => 'advanced',
                'icon' => 'fas fa-cloud',
                'is_built_in' => true,
                'config' => json_encode([
                    'default_nodes' => [
                        ['label' => 'Core Router', 'x' => 700, 'y' => 450],
                        ['label' => 'Edge Router 1', 'x' => 350, 'y' => 300],
                        ['label' => 'Edge Router 2', 'x' => 1050, 'y' => 300],
                        ['label' => 'Edge Router 3', 'x' => 350, 'y' => 600],
                    ],
                    'default_links' => [
                        ['src_node_idx' => 0, 'dst_node_idx' => 1],
                        ['src_node_idx' => 0, 'dst_node_idx' => 2],
                        ['src_node_idx' => 1, 'dst_node_idx' => 3],
                    ],
                ]),
            ],
            [
                'name' => 'blank-canvas',
                'title' => 'Blank Canvas',
                'description' => 'Empty canvas for custom topology',
                'width' => 1200,
                'height' => 800,
                'category' => 'custom',
                'icon' => 'fas fa-plus',
                'is_built_in' => true,
                'config' => json_encode([
                    'default_nodes' => [],
                    'default_links' => [],
                ]),
            ],
        ];

        foreach ($templates as $template) {
            DB::table('wmng_map_templates')->insert($template);
        }

        echo "Created " . count($templates) . " map templates\n";
    }
}
