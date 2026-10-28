<?php
/**
 * WeathermapNG Database Setup Script
 * 
 * This script manually creates the database tables required for WeathermapNG.
 * It's used because LibreNMS local plugins cannot use Laravel migrations.
 * 
 * Usage: php database/setup.php
 */

$libreNMSPath = getenv('LIBRENMS_PATH') ?: '/opt/librenms';
if (!is_file($libreNMSPath . '/vendor/autoload.php') || !is_file($libreNMSPath . '/bootstrap/app.php')) {
    fwrite(STDERR, "Error: Selected LibreNMS bootstrap unavailable; set LIBRENMS_PATH.\n");
    exit(1);
}
try {
    require_once $libreNMSPath . '/vendor/autoload.php';
    $app = require $libreNMSPath . '/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
} catch (Throwable $e) {
    fwrite(STDERR, "Error: LibreNMS bootstrap failed; check its configuration and database connection.\n");
    exit(1);
}
// Upstream console exception handlers must not turn setup failures into a successful exit.
set_exception_handler(function (Throwable $e): void {
    fwrite(STDERR, "Error: Database setup failed; check the selected LibreNMS database/schema permissions.\n");
    exit(1);
});

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "WeathermapNG Database Setup\n";
echo "============================\n\n";

// Function to seed built-in templates
function seedBuiltInTemplates(): void
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
            'description' => 'Star network with central router and 3 edge routers',
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
        $template['created_at'] = date('Y-m-d H:i:s');
        $template['updated_at'] = date('Y-m-d H:i:s');
        \Illuminate\Support\Facades\DB::table('wmng_map_templates')->insert($template);
    }
    echo "  ↳ Seeded " . count($templates) . " built-in templates\n";
}

// Keep normalization in the shared schema setup, used by every install path.
function normalizePluginRegistration(): void
{
    // Laravel facade path
    $schema = Illuminate\Support\Facades\Schema::class;
    $db = Illuminate\Support\Facades\DB::class;

    if (!$schema::hasTable('plugins')) {
        throw new RuntimeException('LibreNMS plugins table unavailable');
    }

    $columns = $schema::getColumnListing('plugins');
    $required = ['plugin_id', 'plugin_name', 'plugin_active', 'version'];
    $missing = array_diff($required, $columns);
    if (!empty($missing)) {
        throw new RuntimeException('LibreNMS plugins schema is incomplete');
    }

    $rows = $db::table('plugins')
        ->where('plugin_name', 'WeathermapNG')
        ->orderBy('plugin_id')
        ->get();

    if ($rows->isEmpty()) {
        echo "  ⚠️  No WeathermapNG plugin row found; skipped plugin row normalization\n";
        return;
    }

    // Prefer version=2 rows; if none exist, promote the kept row to version=2
    $v2Rows = $rows->filter(fn($row) => (int)$row->version === 2)->values();
    $activeV2 = $v2Rows->filter(fn($row) => (int)$row->plugin_active === 1)->values();
    $keep = $activeV2->isNotEmpty()
        ? $activeV2->sortByDesc('plugin_id')->first()
        : ($v2Rows->isNotEmpty() ? $v2Rows->sortByDesc('plugin_id')->first() : $rows->sortByDesc('plugin_id')->first());

    $deleted = $db::transaction(function () use ($db, $keep) {
        $db::table('plugins')->where('plugin_name', 'WeathermapNG')
            ->where('plugin_id', $keep->plugin_id)->update(['plugin_active' => 1, 'version' => 2]);
        return $db::table('plugins')->where('plugin_name', 'WeathermapNG')
            ->where('plugin_id', '!=', $keep->plugin_id)->delete();
    });

    if ($deleted > 0 || (int)$keep->version !== 2) {
        $action = (int)$keep->version !== 2 ? 'promoted v1→v2 and ' : '';
        echo "  ↳ {$action}Cleaned $deleted duplicate WeathermapNG plugin row(s); kept plugin_id {$keep->plugin_id}\n";
    } else {
        echo "  ↳ WeathermapNG plugin registration is already normalized\n";
    }
}

try {
    echo "Checking the selected LibreNMS schema...\n";

    // Check if Schema facade is available
    if (Schema::getFacadeRoot() !== null) {
        echo "Creating database tables...\n\n";

        // Create maps table
        if (!Schema::hasTable('wmng_maps')) {
            Schema::create('wmng_maps', function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
                $t->string('title')->nullable();
                $t->text('description')->nullable();
                $t->unsignedBigInteger('parent_map_id')->nullable();
                $t->index('parent_map_id');
                $t->integer('width')->default(800);
                $t->integer('height')->default(600);
                $t->json('options')->nullable();
                $t->timestamps();
            });
            echo "✓ Created table 'wmng_maps'\n";
        }

        // Create nodes table
        if (!Schema::hasTable('wmng_nodes')) {
            Schema::create('wmng_nodes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('map_id')->constrained('wmng_maps')->onDelete('cascade');
                $t->string('label');
                $t->float('x');
                $t->float('y');
                $t->unsignedBigInteger('device_id')->nullable();
                $t->json('meta')->nullable();
                $t->timestamps();
            });
            echo "✓ Created table 'wmng_nodes'\n";
        }

        // Create links table
        if (!Schema::hasTable('wmng_links')) {
            Schema::create('wmng_links', function (Blueprint $t) {
                $t->id();
                $t->foreignId('map_id')->constrained('wmng_maps')->onDelete('cascade');
                $t->foreignId('src_node_id')->constrained('wmng_nodes');
                $t->foreignId('dst_node_id')->constrained('wmng_nodes');
                $t->unsignedBigInteger('port_id_a')->nullable();
                $t->unsignedBigInteger('port_id_b')->nullable();
                $t->unsignedBigInteger('bandwidth_bps')->nullable();
                $t->json('style')->nullable();
                $t->timestamps();
            });
            echo "✓ Created table 'wmng_links'\n";
        }

        // Create map versions table (for versioning system)
        if (!Schema::hasTable('wmng_map_versions')) {
            Schema::create('wmng_map_versions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('map_id');
                $t->foreign('map_id')->references('id')->on('wmng_maps')->onDelete('cascade');
                $t->string('name')->nullable();
                $t->text('description')->nullable();
                $t->longText('config_snapshot')->nullable();
                $t->string('created_by')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['map_id']);
                $t->index(['created_at']);
            });
            echo "✓ Created table 'wmng_map_versions'\n";
        }

        // Create map templates table
        if (!Schema::hasTable('wmng_map_templates')) {
            Schema::create('wmng_map_templates', function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
                $t->string('title');
                $t->text('description')->nullable();
                $t->integer('width')->default(800);
                $t->integer('height')->default(600);
                $t->json('config')->nullable();
                $t->string('icon')->default('fas fa-map');
                $t->string('category')->default('custom');
                $t->boolean('is_built_in')->default(false);
                $t->timestamps();
            });
            echo "✓ Created table 'wmng_map_templates'\n";
            seedBuiltInTemplates();
        }
        if (!Schema::hasColumn('wmng_maps', 'title')) {
            Schema::table('wmng_maps', function (Blueprint $t) { $t->string('title')->nullable(); });
        }
        if (!Schema::hasColumn('wmng_maps', 'parent_map_id')) {
            Schema::table('wmng_maps', function (Blueprint $t) { $t->unsignedBigInteger('parent_map_id')->nullable()->index(); });
        }
        normalizePluginRegistration();
        echo "\n✅ Database setup completed successfully (Laravel Schema method)!\n";
        exit(0);
    } else {
        throw new Exception("Laravel Schema facade not available");
    }

} catch (Throwable $e) {
    fwrite(STDERR, "Error: Database setup failed; check the selected LibreNMS database/schema permissions.\n");
    exit(1);
}
