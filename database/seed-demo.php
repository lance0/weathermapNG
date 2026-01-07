<?php
/**
 * WeathermapNG Demo Data Seeder
 *
 * Creates a sample network topology for testing and demonstration.
 *
 * Usage: php database/seed-demo.php
 */

// Determine LibreNMS path
$possiblePaths = [
    dirname(__DIR__, 3),
    '/opt/librenms',
    getenv('LIBRENMS_PATH') ?: '',
];

$libreNMSPath = null;
foreach ($possiblePaths as $path) {
    if ($path && file_exists($path . '/vendor/autoload.php')) {
        $libreNMSPath = $path;
        break;
    }
}

if (!$libreNMSPath) {
    echo "Error: Could not find LibreNMS installation.\n";
    exit(1);
}

require_once $libreNMSPath . '/vendor/autoload.php';
$app = require_once $libreNMSPath . '/bootstrap/app.php';

try {
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
} catch (Exception $e) {
    // Fall back to direct PDO
}

echo "WeathermapNG Demo Data Seeder\n";
echo "=============================\n\n";

try {
    // Try Eloquent first
    if (class_exists('Illuminate\Support\Facades\DB')) {
        $db = Illuminate\Support\Facades\DB::class;

        // Check if demo map exists
        $existing = $db::table('wmng_maps')->where('name', 'demo-network')->first();
        if ($existing) {
            echo "Demo map already exists (ID: {$existing->id})\n";
            echo "Delete it first if you want to recreate: DELETE FROM wmng_maps WHERE name='demo-network'\n";
            exit(0);
        }

        // Create demo map
        $mapId = $db::table('wmng_maps')->insertGetId([
            'name' => 'demo-network',
            'title' => 'Demo Network Topology',
            'description' => 'Sample network for testing WeathermapNG',
            'width' => 1200,
            'height' => 700,
            'options' => json_encode(['background' => '#ffffff']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "Created map 'demo-network' (ID: $mapId)\n";

        // Create nodes
        $nodes = [
            ['label' => 'Core Router', 'x' => 600, 'y' => 80],
            ['label' => 'Switch-A', 'x' => 300, 'y' => 250],
            ['label' => 'Switch-B', 'x' => 900, 'y' => 250],
            ['label' => 'Web Server', 'x' => 150, 'y' => 450],
            ['label' => 'DB Server', 'x' => 450, 'y' => 450],
            ['label' => 'App Server', 'x' => 750, 'y' => 450],
            ['label' => 'File Server', 'x' => 1050, 'y' => 450],
            ['label' => 'Firewall', 'x' => 600, 'y' => 620],
        ];

        $nodeIds = [];
        foreach ($nodes as $node) {
            $nodeIds[] = $db::table('wmng_nodes')->insertGetId([
                'map_id' => $mapId,
                'label' => $node['label'],
                'x' => $node['x'],
                'y' => $node['y'],
                'device_id' => null,
                'meta' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "  Created node: {$node['label']}\n";
        }

        // Create links (node IDs are 1-indexed from insertGetId)
        $links = [
            [0, 1, 10000000000],  // Core Router -> Switch-A (10Gbps)
            [0, 2, 10000000000],  // Core Router -> Switch-B (10Gbps)
            [1, 3, 1000000000],   // Switch-A -> Web Server (1Gbps)
            [1, 4, 1000000000],   // Switch-A -> DB Server (1Gbps)
            [2, 5, 1000000000],   // Switch-B -> App Server (1Gbps)
            [2, 6, 1000000000],   // Switch-B -> File Server (1Gbps)
            [0, 7, 10000000000],  // Core Router -> Firewall (10Gbps)
            [1, 2, 10000000000],  // Switch-A <-> Switch-B (10Gbps trunk)
        ];

        foreach ($links as $link) {
            $db::table('wmng_links')->insert([
                'map_id' => $mapId,
                'src_node_id' => $nodeIds[$link[0]],
                'dst_node_id' => $nodeIds[$link[1]],
                'bandwidth_bps' => $link[2],
                'port_id_a' => null,
                'port_id_b' => null,
                'style' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        echo "  Created " . count($links) . " links\n";

        echo "\n✅ Demo data created successfully!\n";
        echo "\nView your demo map at: /plugin/WeathermapNG/embed/$mapId\n";
        echo "\nTip: Enable demo mode for simulated traffic:\n";
        echo "  export WEATHERMAPNG_DEMO_MODE=true\n";
        exit(0);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
