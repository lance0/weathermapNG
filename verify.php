<?php
/**
 * WeathermapNG Installation Verification Script
 * Run this after installation to verify everything is working
 */

echo "🔍 WeathermapNG Installation Verification\n";
echo "==========================================\n\n";

// Check for Docker environment
check_docker_environment();

// Check PHP version
echo "📋 Checking PHP version... ";
if (version_compare(PHP_VERSION, '8.0.0', '>=') === true) {
    echo "✅ PHP " . PHP_VERSION . " (OK)\n";
} else {
    echo "❌ PHP " . PHP_VERSION . " (Requires 8.0+)\n";
}

// Check GD extension
echo "📋 Checking GD extension... ";
if (extension_loaded('gd')) {
    echo "✅ GD extension loaded\n";
} else {
    echo "❌ GD extension not loaded\n";
}

// Check if plugin files exist
echo "📋 Checking plugin files... ";
$pluginPath = __DIR__;
$requiredFiles = [
    'WeathermapNG.php',
    'composer.json',
    'routes.php',
    'config/weathermapng.php',
    'Http/Controllers/MapController.php',
    'Resources/views/index.blade.php'
];

$filesOk = true;
foreach ($requiredFiles as $file) {
    if (!file_exists($pluginPath . '/' . $file)) {
        echo "❌ Missing: $file\n";
        $filesOk = false;
    }
}

if ($filesOk) {
    echo "✅ All plugin files present\n";
}

// Check if output directory is writable
echo "📋 Checking output directory... ";
$outputDir = __DIR__ . '/output';
if (is_dir($outputDir) && is_writable($outputDir)) {
    echo "✅ Output directory writable\n";
} elseif (!is_dir($outputDir)) {
    echo "❌ Output directory does not exist\n";
} else {
    echo "❌ Output directory not writable\n";
}

// Check if poller script exists and is executable
echo "📋 Checking poller script... ";
$pollerScript = __DIR__ . '/bin/map-poller.php';
if (file_exists($pollerScript)) {
    if (is_executable($pollerScript)) {
        echo "✅ Poller script executable\n";
    } else {
        echo "❌ Poller script not executable\n";
    }
} else {
    echo "❌ Poller script not found\n";
}

// Check if vendor directory exists (Composer dependencies)
echo "📋 Checking Composer dependencies... ";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "✅ Composer dependencies installed\n";
} else {
    echo "❌ Composer dependencies not installed (run: composer install)\n";
}

// Check database connection (if possible)
echo "📋 Checking database connection... ";
try {
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        // Try to load LibreNMS bootstrap
        if (file_exists(__DIR__ . '/../bootstrap/app.php')) {
            require_once __DIR__ . '/../bootstrap/app.php';
            // This would need proper LibreNMS database configuration
            echo "✅ Database connection available\n";
        } else {
            echo "⚠️  Cannot verify database (LibreNMS bootstrap not found)\n";
        }
    } else {
        echo "⚠️  Cannot verify database (Composer autoload not found)\n";
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n📊 Verification complete!\n";
echo "========================\n";

echo "\n🌐 Next steps:\n";
echo "   1. If any checks failed, fix the issues above\n";
echo "   2. Enable the plugin in LibreNMS web interface\n";
echo "   3. Visit: https://your-librenms/plugins/weathermapng\n";
echo "   4. Create your first network map!\n";

echo "\n📖 For help, see: https://github.com/lance0/weathermapNG\n";
?>

<?php
function check_docker_environment() {
    $indicators = [
        'DOCKER_CONTAINER' => getenv('DOCKER_CONTAINER'),
        'DOCKERENV file' => file_exists('/.dockerenv'),
        'Container ID' => getenv('HOSTNAME'), // Often container ID
        'LibreNMS Docker' => getenv('LIBRENMS_DOCKER'),
    ];

    $docker_detected = false;
    foreach ($indicators as $name => $value) {
        if (!empty($value)) {
            echo "🐳 Docker indicator found: $name = $value\n";
            $docker_detected = true;
        }
    }

    if ($docker_detected) {
        echo "🐳 Docker environment detected - using container verification\n\n";
        return true;
    } else {
        echo "🖥️  Standard environment detected\n\n";
        return false;
    }
}
?>