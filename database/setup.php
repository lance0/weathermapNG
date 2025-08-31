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
require_once $libreNMSPath . '/bootstrap/app.php';

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "WeathermapNG Database Setup\n";
echo "============================\n\n";

try {
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
        echo "\nAll tables already exist. No action needed.\n";
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
    
    echo "\n✅ Database setup completed successfully!\n";
    exit(0);
    
} catch (Exception $e) {
    echo "\n❌ Error creating tables: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Check database connection in LibreNMS\n";
    echo "2. Ensure LibreNMS user has CREATE TABLE permissions\n";
    echo "3. Try running the SQL manually from database/schema.sql\n";
    exit(1);
}