<?php
/**
 * WeathermapNG Database Setup Script
 * 
 * This script manually creates the database tables required for WeathermapNG.
 * It's used because LibreNMS local plugins cannot use Laravel migrations.
 * 
 * Usage: php database/setup.php
 */

// Determine LibreNMS path
$possiblePaths = [
    dirname(__DIR__, 3),  // /opt/librenms from plugin directory
    '/opt/librenms',
    '/usr/local/librenms',
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
    echo "Please set LIBRENMS_PATH environment variable.\n";
    exit(1);
}

// Load LibreNMS bootstrap
require_once $libreNMSPath . '/vendor/autoload.php';

$app = require_once $libreNMSPath . '/bootstrap/app.php';

// Bootstrap Laravel application to initialize facades
try {
    if (method_exists($app, 'make')) {
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        if (method_exists($kernel, 'bootstrap')) {
            $kernel->bootstrap();
        }
    }
} catch (Exception $e) {
    // If Laravel bootstrap fails, we'll fall back to direct SQL
    echo "⚠️  Laravel bootstrap failed, will use direct SQL method\n";
}

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
    ];

    foreach ($templates as $template) {
        $template['created_at'] = date('Y-m-d H:i:s');
        $template['updated_at'] = date('Y-m-d H:i:s');
        \Illuminate\Support\Facades\DB::table('wmng_map_templates')->insert($template);
    }
    echo "  ↳ Seeded " . count($templates) . " built-in templates\n";
}

// Function to seed built-in templates using PDO (for direct SQL fallback)
function seedBuiltInTemplatesPdo(PDO $pdo): void
{
    $templates = [
        ['small-network', 'Small Network', 'Simple 2-router network with direct connection', 800, 600, 'basic', 'fas fa-network-wired', '{"default_nodes":[{"label":"Router A","x":150,"y":300},{"label":"Router B","x":650,"y":300}],"default_links":[{"src_node_idx":0,"dst_node_idx":1}]}'],
        ['star-topology', 'Star Topology', 'Star network with central router and 3 edge routers', 1000, 700, 'basic', 'fas fa-project-diagram', '{"default_nodes":[{"label":"Core Router","x":500,"y":350},{"label":"Edge Router 1","x":250,"y":150},{"label":"Edge Router 2","x":750,"y":150},{"label":"Edge Router 3","x":500,"y":550}],"default_links":[{"src_node_idx":0,"dst_node_idx":1},{"src_node_idx":0,"dst_node_idx":2},{"src_node_idx":0,"dst_node_idx":3}]}'],
        ['redundant-links', 'Redundant Links', 'Dual-homed network with redundant paths', 1000, 800, 'advanced', 'fas fa-server', '{"default_nodes":[{"label":"Site A Router 1","x":200,"y":300},{"label":"Site A Router 2","x":800,"y":300},{"label":"Site B Router 1","x":200,"y":500},{"label":"Site B Router 2","x":800,"y":500}],"default_links":[{"src_node_idx":0,"dst_node_idx":2},{"src_node_idx":1,"dst_node_idx":3},{"src_node_idx":2,"dst_node_idx":3},{"src_node_idx":0,"dst_node_idx":1}]}'],
        ['isp-backbone', 'ISP Backbone', 'Multi-tier ISP backbone network', 1400, 900, 'advanced', 'fas fa-cloud', '{"default_nodes":[{"label":"Core Router","x":700,"y":450},{"label":"Edge Router 1","x":350,"y":300},{"label":"Edge Router 2","x":1050,"y":300},{"label":"Edge Router 3","x":350,"y":600}],"default_links":[{"src_node_idx":0,"dst_node_idx":1},{"src_node_idx":0,"dst_node_idx":2},{"src_node_idx":1,"dst_node_idx":3}]}'],
        ['blank-canvas', 'Blank Canvas', 'Empty canvas for custom topology', 1200, 800, 'custom', 'fas fa-plus', '{"default_nodes":[],"default_links":[]}'],
    ];

    $stmt = $pdo->prepare("INSERT INTO `wmng_map_templates` (`name`, `title`, `description`, `width`, `height`, `category`, `icon`, `config`, `is_built_in`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");

    foreach ($templates as $t) {
        $stmt->execute($t);
    }
    echo "  ↳ Seeded " . count($templates) . " built-in templates\n";
}

try {
    // Method 1: Try Laravel Schema approach
    echo "🔄 Attempting Laravel Schema method...\n";

    // Check if Schema facade is available
    if (class_exists('Illuminate\Support\Facades\Schema') && method_exists('Illuminate\Support\Facades\Schema', 'hasTable')) {
        // Check if tables already exist
        $tablesExist = 0;
        if (Schema::hasTable('wmng_maps')) {
            echo "✓ Table 'wmng_maps' already exists\n";
            $tablesExist++;
        }
        if (Schema::hasTable('wmng_nodes')) {
            echo "✓ Table 'wmng_nodes' already exists\n";
            $tablesExist++;
        }
        if (Schema::hasTable('wmng_links')) {
            echo "✓ Table 'wmng_links' already exists\n";
            $tablesExist++;
        }
        if (Schema::hasTable('wmng_map_versions')) {
            echo "✓ Table 'wmng_map_versions' already exists\n";
            $tablesExist++;
        }
        if (Schema::hasTable('wmng_map_templates')) {
            echo "✓ Table 'wmng_map_templates' already exists\n";
            $tablesExist++;
        }

        if ($tablesExist === 5) {
            // Check for missing columns in existing tables
            if (!Schema::hasColumn('wmng_maps', 'title')) {
                Schema::table('wmng_maps', function (Blueprint $t) {
                    $t->string('title')->nullable()->after('name');
                });
                echo "✓ Added 'title' column to 'wmng_maps'\n";
            }

            echo "\n✅ All tables already exist and are up to date.\n";
            exit(0);
        }

        // Check if versions table is missing (upgrade from older install)
        if ($tablesExist >= 3 && !Schema::hasTable('wmng_map_versions')) {
            echo "🔄 Adding missing wmng_map_versions table...\n";
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
            $tablesExist++;
        }

        // Check if templates table is missing (upgrade from older install)
        if ($tablesExist >= 3 && !Schema::hasTable('wmng_map_templates')) {
            echo "🔄 Adding missing wmng_map_templates table...\n";
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
            $tablesExist++;
        }

        // If we upgraded tables, exit successfully
        if ($tablesExist === 5) {
            echo "\n✅ Database upgraded successfully!\n";
            exit(0);
        }

        echo "Creating database tables...\n\n";

        // Create maps table
        if (!Schema::hasTable('wmng_maps')) {
            Schema::create('wmng_maps', function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
                $t->string('title')->nullable();
                $t->text('description')->nullable();
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

        echo "\n✅ Database setup completed successfully (Laravel Schema method)!\n";
        exit(0);
    } else {
        throw new Exception("Laravel Schema facade not available");
    }

} catch (Exception $e) {
    echo "⚠️  Laravel Schema method failed: " . $e->getMessage() . "\n";
    echo "🔄 Falling back to direct SQL method...\n\n";

    // Method 2: Direct SQL fallback
    try {
        // Get database configuration from environment or defaults
        $dbHost = getenv('DB_HOST') ?: 'localhost';
        $dbName = getenv('DB_DATABASE') ?: 'librenms';
        $dbUser = getenv('DB_USERNAME') ?: 'librenms';
        $dbPass = getenv('DB_PASSWORD') ?: 'librenms';

        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if tables already exist
        $result = $pdo->query("SHOW TABLES LIKE 'wmng_%'");
        $existingTables = $result->fetchAll(PDO::FETCH_COLUMN);

        if (count($existingTables) >= 5) {
            // Check for missing columns
            $columns = $pdo->query("SHOW COLUMNS FROM `wmng_maps` LIKE 'title'")->fetchAll();
            if (empty($columns)) {
                $pdo->exec("ALTER TABLE `wmng_maps` ADD COLUMN `title` varchar(255) DEFAULT NULL AFTER `name` ");
                echo "✓ Added 'title' column to 'wmng_maps' (Direct SQL)\n";
            }

            echo "✅ All tables already exist and are up to date (Direct SQL method).\n";
            exit(0);
        }

        $upgraded = false;

        // Check if versions table is missing (upgrade from older install)
        if (count($existingTables) >= 3 && !in_array('wmng_map_versions', $existingTables)) {
            echo "🔄 Adding missing wmng_map_versions table...\n";
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `wmng_map_versions` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `map_id` bigint unsigned NOT NULL,
                  `name` varchar(255) DEFAULT NULL,
                  `description` text,
                  `config_snapshot` longtext,
                  `created_by` varchar(255) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `wmng_map_versions_map_id_index` (`map_id`),
                  KEY `wmng_map_versions_created_at_index` (`created_at`),
                  CONSTRAINT `wmng_map_versions_map_id_foreign` FOREIGN KEY (`map_id`) REFERENCES `wmng_maps` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            echo "✓ Created table 'wmng_map_versions'\n";
            $upgraded = true;
        }

        // Check if templates table is missing (upgrade from older install)
        if (count($existingTables) >= 3 && !in_array('wmng_map_templates', $existingTables)) {
            echo "🔄 Adding missing wmng_map_templates table...\n";
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `wmng_map_templates` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) NOT NULL,
                  `title` varchar(255) NOT NULL,
                  `description` text,
                  `width` int NOT NULL DEFAULT 800,
                  `height` int NOT NULL DEFAULT 600,
                  `config` json DEFAULT NULL,
                  `icon` varchar(255) DEFAULT 'fas fa-map',
                  `category` varchar(255) DEFAULT 'custom',
                  `is_built_in` tinyint(1) DEFAULT 0,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `wmng_map_templates_name_unique` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            echo "✓ Created table 'wmng_map_templates'\n";
            seedBuiltInTemplatesPdo($pdo);
            $upgraded = true;
        }

        if ($upgraded) {
            echo "✅ Database upgraded successfully (Direct SQL method).\n";
            exit(0);
        }

        echo "Creating database tables using direct SQL...\n\n";

        // Create tables using direct SQL
        $sql = "
        CREATE TABLE IF NOT EXISTS `wmng_maps` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `title` varchar(255) DEFAULT NULL,
          `description` text,
          `width` int NOT NULL DEFAULT 800,
          `height` int NOT NULL DEFAULT 600,
          `options` json DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `wmng_maps_name_unique` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `wmng_nodes` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `map_id` bigint unsigned NOT NULL,
          `label` varchar(255) NOT NULL,
          `x` float NOT NULL,
          `y` float NOT NULL,
          `device_id` bigint unsigned DEFAULT NULL,
          `meta` json DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `wmng_nodes_map_id_foreign` (`map_id`),
          CONSTRAINT `wmng_nodes_map_id_foreign` FOREIGN KEY (`map_id`) REFERENCES `wmng_maps` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `wmng_links` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `map_id` bigint unsigned NOT NULL,
          `src_node_id` bigint unsigned NOT NULL,
          `dst_node_id` bigint unsigned NOT NULL,
          `port_id_a` bigint unsigned DEFAULT NULL,
          `port_id_b` bigint unsigned DEFAULT NULL,
          `bandwidth_bps` bigint unsigned DEFAULT NULL,
          `style` json DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `wmng_links_map_id_foreign` (`map_id`),
          KEY `wmng_links_src_node_id_foreign` (`src_node_id`),
          KEY `wmng_links_dst_node_id_foreign` (`dst_node_id`),
          CONSTRAINT `wmng_links_map_id_foreign` FOREIGN KEY (`map_id`) REFERENCES `wmng_maps` (`id`) ON DELETE CASCADE,
          CONSTRAINT `wmng_links_src_node_id_foreign` FOREIGN KEY (`src_node_id`) REFERENCES `wmng_nodes` (`id`),
          CONSTRAINT `wmng_links_dst_node_id_foreign` FOREIGN KEY (`dst_node_id`) REFERENCES `wmng_nodes` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `wmng_map_versions` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `map_id` bigint unsigned NOT NULL,
          `name` varchar(255) DEFAULT NULL,
          `description` text,
          `config_snapshot` longtext,
          `created_by` varchar(255) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `wmng_map_versions_map_id_index` (`map_id`),
          KEY `wmng_map_versions_created_at_index` (`created_at`),
          CONSTRAINT `wmng_map_versions_map_id_foreign` FOREIGN KEY (`map_id`) REFERENCES `wmng_maps` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `wmng_map_templates` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `title` varchar(255) NOT NULL,
          `description` text,
          `width` int NOT NULL DEFAULT 800,
          `height` int NOT NULL DEFAULT 600,
          `config` json DEFAULT NULL,
          `icon` varchar(255) DEFAULT 'fas fa-map',
          `category` varchar(255) DEFAULT 'custom',
          `is_built_in` tinyint(1) DEFAULT 0,
          `created_at` timestamp NULL DEFAULT NULL,
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `wmng_map_templates_name_unique` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $pdo->exec($sql);
        seedBuiltInTemplatesPdo($pdo);
        echo "✅ Database tables created successfully (Direct SQL method)!\n";
        exit(0);

    } catch (PDOException $pdoError) {
        echo "❌ Direct SQL method also failed: " . $pdoError->getMessage() . "\n";
        echo "\n💡 Troubleshooting:\n";
        echo "1. Check database connection: mysql -u $dbUser -p$dbPass -h $dbHost $dbName\n";
        echo "2. Ensure database user has CREATE TABLE permissions\n";
        echo "3. Try running the SQL manually: mysql -u $dbUser -p$dbPass $dbName < database/schema.sql\n";
        echo "4. Check LibreNMS database configuration in config/database.php\n";
        exit(1);
    }
}