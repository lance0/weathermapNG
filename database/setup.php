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

        if ($tablesExist === 3) {
            echo "\n✅ All tables already exist. No action needed.\n";
            exit(0);
        }

        echo "Creating database tables...\n\n";

        // Create maps table
        if (!Schema::hasTable('wmng_maps')) {
            Schema::create('wmng_maps', function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
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

        if (count($existingTables) >= 3) {
            echo "✅ All tables already exist (Direct SQL method).\n";
            exit(0);
        }

        echo "Creating database tables using direct SQL...\n\n";

        // Create tables using direct SQL
        $sql = "
        CREATE TABLE IF NOT EXISTS `wmng_maps` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
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
        ";

        $pdo->exec($sql);
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