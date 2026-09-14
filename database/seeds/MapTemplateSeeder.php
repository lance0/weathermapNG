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
            [
                'name' => 'data-center',
                'title' => 'Data Center',
                'description' => 'Dual core switches with access layer and server racks',
                'width' => 1200,
                'height' => 900,
                'category' => 'data center',
                'icon' => 'fas fa-database',
                'is_built_in' => true,
                'config' => json_encode([
                    'default_nodes' => [
                        ['label' => 'Core Sw A', 'x' => 450, 'y' => 200],
                        ['label' => 'Core Sw B', 'x' => 750, 'y' => 200],
                        ['label' => 'Access Sw A', 'x' => 300, 'y' => 500],
                        ['label' => 'Access Sw B', 'x' => 600, 'y' => 500],
                        ['label' => 'Access Sw C', 'x' => 900, 'y' => 500],
                        ['label' => 'Rack 1', 'x' => 300, 'y' => 750],
                        ['label' => 'Rack 2', 'x' => 600, 'y' => 750],
                        ['label' => 'Rack 3', 'x' => 900, 'y' => 750],
                    ],
                    'default_links' => [
                        ['src_node_idx' => 0, 'dst_node_idx' => 1],
                        ['src_node_idx' => 0, 'dst_node_idx' => 2],
                        ['src_node_idx' => 0, 'dst_node_idx' => 3],
                        ['src_node_idx' => 1, 'dst_node_idx' => 4],
                        ['src_node_idx' => 1, 'dst_node_idx' => 3],
                        ['src_node_idx' => 2, 'dst_node_idx' => 5],
                        ['src_node_idx' => 3, 'dst_node_idx' => 6],
                        ['src_node_idx' => 4, 'dst_node_idx' => 7],
                    ],
                ]),
            ],
            [
                'name' => 'wan-mpls',
                'title' => 'WAN / MPLS',
                'description' => 'Multi-site WAN over an MPLS cloud with dual exits',
                'width' => 1400,
                'height' => 900,
                'category' => 'wan',
                'icon' => 'fas fa-globe',
                'is_built_in' => true,
                'config' => json_encode([
                    'default_nodes' => [
                        ['label' => 'MPLS Cloud', 'x' => 700, 'y' => 400],
                        ['label' => 'HQ Edge', 'x' => 250, 'y' => 250],
                        ['label' => 'Branch Edge', 'x' => 1150, 'y' => 250],
                        ['label' => 'DC Edge', 'x' => 250, 'y' => 600],
                        ['label' => 'Remote Site', 'x' => 1150, 'y' => 600],
                    ],
                    'default_links' => [
                        ['src_node_idx' => 0, 'dst_node_idx' => 1],
                        ['src_node_idx' => 0, 'dst_node_idx' => 2],
                        ['src_node_idx' => 0, 'dst_node_idx' => 3],
                        ['src_node_idx' => 0, 'dst_node_idx' => 4],
                        ['src_node_idx' => 1, 'dst_node_idx' => 3],
                        ['src_node_idx' => 2, 'dst_node_idx' => 4],
                    ],
                ]),
            ],
            [
                'name' => 'campus',
                'title' => 'Campus Network',
                'description' => 'Collapsed-core campus: core, buildings, and floor switches',
                'width' => 1300,
                'height' => 900,
                'category' => 'campus',
                'icon' => 'fas fa-school',
                'is_built_in' => true,
                'config' => json_encode([
                    'default_nodes' => [
                        ['label' => 'Core 1', 'x' => 500, 'y' => 250],
                        ['label' => 'Core 2', 'x' => 800, 'y' => 250],
                        ['label' => 'Building A Dist', 'x' => 300, 'y' => 500],
                        ['label' => 'Building B Dist', 'x' => 650, 'y' => 500],
                        ['label' => 'Building C Dist', 'x' => 1000, 'y' => 500],
                        ['label' => 'Bldg A Floor 1', 'x' => 300, 'y' => 720],
                        ['label' => 'Bldg A Floor 2', 'x' => 300, 'y' => 790],
                        ['label' => 'Bldg B Floor 1', 'x' => 650, 'y' => 720],
                        ['label' => 'Bldg B Floor 2', 'x' => 650, 'y' => 790],
                        ['label' => 'Bldg C Floor 1', 'x' => 1000, 'y' => 720],
                        ['label' => 'Bldg C Floor 2', 'x' => 1000, 'y' => 790],
                    ],
                    'default_links' => [
                        ['src_node_idx' => 0, 'dst_node_idx' => 1],
                        ['src_node_idx' => 0, 'dst_node_idx' => 2],
                        ['src_node_idx' => 0, 'dst_node_idx' => 3],
                        ['src_node_idx' => 1, 'dst_node_idx' => 3],
                        ['src_node_idx' => 1, 'dst_node_idx' => 4],
                        ['src_node_idx' => 2, 'dst_node_idx' => 5],
                        ['src_node_idx' => 2, 'dst_node_idx' => 6],
                        ['src_node_idx' => 3, 'dst_node_idx' => 7],
                        ['src_node_idx' => 3, 'dst_node_idx' => 8],
                        ['src_node_idx' => 4, 'dst_node_idx' => 9],
                        ['src_node_idx' => 4, 'dst_node_idx' => 10],
                    ],
                ]),
            ],
            [
                'name' => 'branch-office',
                'title' => 'Branch Office',
                'description' => 'Single branch: edge router, switch, and endpoints',
                'width' => 900,
                'height' => 600,
                'category' => 'basic',
                'icon' => 'fas fa-building',
                'is_built_in' => true,
                'config' => json_encode([
                    'default_nodes' => [
                        ['label' => 'WAN Router', 'x' => 450, 'y' => 150],
                        ['label' => 'Edge Firewall', 'x' => 450, 'y' => 300],
                        ['label' => 'Branch Switch', 'x' => 450, 'y' => 450],
                        ['label' => 'AP 1', 'x' => 200, 'y' => 500],
                        ['label' => 'AP 2', 'x' => 700, 'y' => 500],
                    ],
                    'default_links' => [
                        ['src_node_idx' => 0, 'dst_node_idx' => 1],
                        ['src_node_idx' => 1, 'dst_node_idx' => 2],
                        ['src_node_idx' => 2, 'dst_node_idx' => 3],
                        ['src_node_idx' => 2, 'dst_node_idx' => 4],
                    ],
                ]),
            ],
        ];
        foreach ($templates as $template) {
            DB::table('wmng_map_templates')->insert($template);
        }

        echo "Created " . count($templates) . " map templates\n";
    }
}
