#!/usr/bin/env php
<?php
/**
 * WeathermapNG Deployment Verification Script
 * Checks that the plugin is properly installed and configured for LibreNMS v2
 */

// Colors for output
define('RED', "\033[0;31m");
define('GREEN', "\033[0;32m");
define('YELLOW', "\033[1;33m");
define('NC', "\033[0m"); // No Color

$errors = 0;
$warnings = 0;

function pass($message) {
    echo GREEN . "[✓] " . NC . $message . PHP_EOL;
}

function fail($message) {
    global $errors;
    $errors++;
    echo RED . "[✗] " . NC . $message . PHP_EOL;
}

function warn($message) {
    global $warnings;
    $warnings++;
    echo YELLOW . "[!] " . NC . $message . PHP_EOL;
}

function info($message) {
    echo "    " . $message . PHP_EOL;
}

echo PHP_EOL;
echo "========================================" . PHP_EOL;
echo "  WeathermapNG v2 Deployment Checker" . PHP_EOL;
echo "========================================" . PHP_EOL;
echo PHP_EOL;

// 1. Check PHP version
echo "Checking PHP version..." . PHP_EOL;
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    pass("PHP version " . PHP_VERSION . " meets requirements");
} else {
    fail("PHP version " . PHP_VERSION . " is too old (need 8.0+)");
}

// 2. Check required PHP extensions
echo PHP_EOL . "Checking PHP extensions..." . PHP_EOL;
$required_extensions = ['gd', 'json', 'pdo', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        pass("Extension '$ext' is loaded");
    } else {
        fail("Extension '$ext' is missing");
    }
}

// 3. Check LibreNMS bootstrap
echo PHP_EOL . "Checking LibreNMS integration..." . PHP_EOL;
$librenms_base = getenv('LIBRENMS_PATH') ?: '/opt/librenms';

if (file_exists($librenms_base . '/vendor/autoload.php')) {
    require_once $librenms_base . '/vendor/autoload.php';
    pass("LibreNMS autoloader found");
} else {
    fail("LibreNMS autoloader not found at $librenms_base/vendor/autoload.php");
    die("Cannot continue without LibreNMS\n");
}

if (file_exists($librenms_base . '/includes/init.php')) {
    require_once $librenms_base . '/includes/init.php';
    pass("LibreNMS initialized");
} else {
    warn("LibreNMS init.php not found - some checks will be skipped");
}

// 4. Check plugin files
echo PHP_EOL . "Checking plugin files..." . PHP_EOL;
$plugin_dir = dirname(__FILE__);

$required_files = [
    'plugin.json' => 'Plugin manifest',
    'routes.php' => 'Route definitions',
    'config/weathermapng.php' => 'Configuration file',
    'app/Plugins/WeathermapNG/Menu.php' => 'Menu hook',
    'app/Plugins/WeathermapNG/Page.php' => 'Page hook',
    'app/Plugins/WeathermapNG/Settings.php' => 'Settings hook',
];

foreach ($required_files as $file => $description) {
    if (file_exists($plugin_dir . '/' . $file)) {
        pass("$description exists");
    } else {
        fail("$description missing: $file");
    }
}

// 5. Check hook classes
echo PHP_EOL . "Checking hook classes..." . PHP_EOL;
$hook_classes = [
    'App\Plugins\WeathermapNG\Menu',
    'App\Plugins\WeathermapNG\Page',
    'App\Plugins\WeathermapNG\Settings',
    'App\Plugins\WeathermapNG\DeviceOverview',
    'App\Plugins\WeathermapNG\PortTab',
];

foreach ($hook_classes as $class) {
    if (class_exists($class)) {
        pass("Hook class $class exists");
        
        // Check if it extends the right base class
        $reflection = new ReflectionClass($class);
        $parent = $reflection->getParentClass();
        if ($parent) {
            info("Extends: " . $parent->getName());
        }
    } else {
        fail("Hook class $class not found");
    }
}

// 6. Check database tables
echo PHP_EOL . "Checking database..." . PHP_EOL;
if (class_exists('\Illuminate\Support\Facades\Schema')) {
    $tables = ['wmng_maps', 'wmng_nodes', 'wmng_links'];
    foreach ($tables as $table) {
        if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
            pass("Table '$table' exists");
        } else {
            fail("Table '$table' does not exist - run migrations");
        }
    }
} else {
    warn("Cannot check database - Schema facade not available");
}

// 7. Check permissions
echo PHP_EOL . "Checking permissions..." . PHP_EOL;
$writable_dirs = [
    'output',
    'output/maps',
    'output/cache',
];

foreach ($writable_dirs as $dir) {
    $path = $plugin_dir . '/' . $dir;
    if (!file_exists($path)) {
        warn("Directory '$dir' does not exist");
        if (mkdir($path, 0755, true)) {
            info("Created $dir");
        }
    } elseif (is_writable($path)) {
        pass("Directory '$dir' is writable");
    } else {
        fail("Directory '$dir' is not writable");
    }
}

// 8. Check views
echo PHP_EOL . "Checking view files..." . PHP_EOL;
$view_files = [
    'Resources/views/weathermapng/menu.blade.php',
    'Resources/views/weathermapng/page.blade.php',
    'Resources/views/weathermapng/settings.blade.php',
];

foreach ($view_files as $view) {
    if (file_exists($plugin_dir . '/' . $view)) {
        pass("View '$view' exists");
    } else {
        warn("View '$view' missing");
    }
}

// 9. Check routes
echo PHP_EOL . "Checking routes..." . PHP_EOL;
$routes_file = $plugin_dir . '/routes.php';
if (file_exists($routes_file)) {
    $routes_content = file_get_contents($routes_file);
    
    // Check for v2 routes
    if (strpos($routes_content, '/plugin/WeathermapNG') !== false || 
        strpos($routes_content, 'plugin/WeathermapNG') !== false) {
        pass("Routes use v2 pattern (/plugin/WeathermapNG)");
    } else {
        warn("Routes may not be using v2 pattern");
    }
    
    // Check for auth middleware
    if (strpos($routes_content, "'auth'") !== false || 
        strpos($routes_content, '"auth"') !== false) {
        pass("Routes use auth middleware");
    } else {
        warn("Routes may not be protected by auth");
    }
}

// 10. Test API endpoint
echo PHP_EOL . "Testing API endpoints..." . PHP_EOL;
// This would need to be done via HTTP request in real deployment
info("Manual test: Visit http://your-server/plugin/WeathermapNG");
info("Manual test: Check for 'Network Maps' in menu");

// Summary
echo PHP_EOL;
echo "========================================" . PHP_EOL;
if ($errors === 0 && $warnings === 0) {
    echo GREEN . "  All checks passed!" . NC . PHP_EOL;
} else {
    if ($errors > 0) {
        echo RED . "  $errors errors found" . NC . PHP_EOL;
    }
    if ($warnings > 0) {
        echo YELLOW . "  $warnings warnings found" . NC . PHP_EOL;
    }
}
echo "========================================" . PHP_EOL;
echo PHP_EOL;

if ($errors > 0) {
    echo "To fix errors, run:" . PHP_EOL;
    echo "  ./deploy.sh" . PHP_EOL;
    echo PHP_EOL;
    exit(1);
}

exit(0);