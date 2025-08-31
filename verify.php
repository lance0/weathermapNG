#!/usr/bin/env php
<?php
/**
 * WeathermapNG Installation Verification & Repair Script
 * Usage: php verify.php [--fix] [--quiet]
 */

// Parse command line arguments
$options = getopt('', ['fix', 'quiet', 'help']);
$FIX_MODE = isset($options['fix']);
$QUIET_MODE = isset($options['quiet']);

if (isset($options['help'])) {
    echo "WeathermapNG Verification Script\n";
    echo "Usage: php verify.php [options]\n\n";
    echo "Options:\n";
    echo "  --fix     Attempt to fix issues automatically\n";
    echo "  --quiet   Suppress non-error output\n";
    echo "  --help    Show this help message\n";
    exit(0);
}

// Color codes for output
$RED = "\033[0;31m";
$GREEN = "\033[0;32m";
$YELLOW = "\033[1;33m";
$BLUE = "\033[0;34m";
$NC = "\033[0m"; // No Color

$issues_found = 0;
$issues_fixed = 0;

function output($message, $type = 'info') {
    global $QUIET_MODE, $RED, $GREEN, $YELLOW, $BLUE, $NC;
    
    if ($QUIET_MODE && $type !== 'error') {
        return;
    }
    
    switch ($type) {
        case 'success':
            echo "{$GREEN}✅ $message{$NC}\n";
            break;
        case 'error':
            echo "{$RED}❌ $message{$NC}\n";
            break;
        case 'warning':
            echo "{$YELLOW}⚠️  $message{$NC}\n";
            break;
        case 'info':
        default:
            echo "{$BLUE}ℹ️  $message{$NC}\n";
            break;
    }
}

function check_and_fix($check_name, $check_function, $fix_function = null) {
    global $FIX_MODE, $issues_found, $issues_fixed;
    
    output("Checking $check_name...", 'info');
    
    $result = $check_function();
    
    if ($result === true) {
        output("$check_name: OK", 'success');
        return true;
    } else {
        $issues_found++;
        output("$check_name: FAILED - $result", 'error');
        
        if ($FIX_MODE && $fix_function !== null) {
            output("Attempting to fix $check_name...", 'warning');
            $fix_result = $fix_function();
            if ($fix_result === true) {
                output("$check_name: FIXED", 'success');
                $issues_fixed++;
                return true;
            } else {
                output("Could not fix $check_name: $fix_result", 'error');
                return false;
            }
        }
        
        return false;
    }
}

// Start verification
if (!$QUIET_MODE) {
    echo "🔍 WeathermapNG Installation Verification\n";
    echo "==========================================\n";
    if ($FIX_MODE) {
        echo "🔧 Fix mode enabled - will attempt repairs\n";
    }
    echo "\n";
}

// Check Docker environment
$docker_detected = false;
$indicators = [
    'DOCKER_CONTAINER' => getenv('DOCKER_CONTAINER'),
    'DOCKERENV file' => file_exists('/.dockerenv'),
    'LibreNMS Docker' => getenv('LIBRENMS_DOCKER'),
];

foreach ($indicators as $name => $value) {
    if (!empty($value)) {
        output("Docker environment detected: $name", 'info');
        $docker_detected = true;
        break;
    }
}

if (!$docker_detected) {
    output("Standard host environment detected", 'info');
}

// 1. PHP Version Check
check_and_fix('PHP Version', function() {
    if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
        return true;
    }
    return "PHP " . PHP_VERSION . " found, requires 8.0+";
}, null); // Can't auto-fix PHP version

// 2. PHP Extensions Check
$required_extensions = ['gd', 'json', 'mbstring'];
foreach ($required_extensions as $ext) {
    check_and_fix("PHP Extension: $ext", function() use ($ext) {
        if (extension_loaded($ext)) {
            return true;
        }
        return "Extension not loaded";
    }, null); // Can't auto-install PHP extensions
}

// Check PDO core (should be present with php-common)
check_and_fix("PHP Extension: PDO", function() {
    // extension_loaded is case-insensitive
    if (extension_loaded('pdo')) {
        return true;
    }
    // Get PHP version for accurate package suggestion
    $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    return "PDO not loaded - install php{$phpVersion}-common";
}, null);

// Check PDO MySQL driver
check_and_fix("PHP Extension: pdo_mysql", function() {
    if (extension_loaded('pdo_mysql')) {
        return true;
    }
    // Get PHP version for accurate package suggestion
    $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    return "PDO MySQL not loaded - install php{$phpVersion}-mysql";
}, null);

// 3. Required Files Check
$plugin_path = __DIR__;
$required_files = [
    'WeathermapNG.php',
    'composer.json',
    'routes.php',
    'Http/Controllers/MapController.php',
];

foreach ($required_files as $file) {
    check_and_fix("Required file: $file", function() use ($plugin_path, $file) {
        if (file_exists($plugin_path . '/' . $file)) {
            return true;
        }
        return "File missing";
    }, null); // Can't recreate missing source files
}

// 4. Configuration File Check
check_and_fix('Configuration file', function() use ($plugin_path) {
    if (file_exists($plugin_path . '/config/weathermapng.php')) {
        return true;
    }
    return "Configuration file missing";
}, function() use ($plugin_path) {
    // Create default configuration
    $config_dir = $plugin_path . '/config';
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }
    
    $default_config = <<<'PHP'
<?php
return [
    'default_width' => 800,
    'default_height' => 600,
    'poll_interval' => 300,
    'thresholds' => [50, 80, 95],
    'rrd_base' => '/opt/librenms/rrd',
    'enable_local_rrd' => true,
    'enable_api_fallback' => true,
    'cache_ttl' => 300,
];
PHP;
    
    if (file_put_contents($config_dir . '/weathermapng.php', $default_config)) {
        return true;
    }
    return "Failed to create configuration file";
});

// 5. Output Directory Check
check_and_fix('Output directory', function() use ($plugin_path) {
    $output_dir = $plugin_path . '/output';
    if (is_dir($output_dir) && is_writable($output_dir)) {
        return true;
    } elseif (!is_dir($output_dir)) {
        return "Directory does not exist";
    } else {
        return "Directory not writable";
    }
}, function() use ($plugin_path) {
    $output_dir = $plugin_path . '/output';
    
    // Create directory if it doesn't exist
    if (!is_dir($output_dir)) {
        if (mkdir($output_dir, 0775, true)) {
            return true;
        }
        return "Failed to create directory";
    }
    
    // Try to fix permissions
    if (chmod($output_dir, 0775)) {
        if (is_writable($output_dir)) {
            return true;
        }
    }
    
    return "Failed to fix permissions - may need sudo";
});

// 6. Poller Script Check
check_and_fix('Poller script', function() use ($plugin_path) {
    $poller_script = $plugin_path . '/bin/map-poller.php';
    if (file_exists($poller_script)) {
        if (is_executable($poller_script)) {
            return true;
        }
        return "Script not executable";
    }
    return "Script not found";
}, function() use ($plugin_path) {
    $poller_script = $plugin_path . '/bin/map-poller.php';
    
    if (!file_exists($poller_script)) {
        return "Cannot create missing script";
    }
    
    // Make executable
    if (chmod($poller_script, 0755)) {
        return true;
    }
    
    return "Failed to make executable - may need sudo";
});

// 7. Composer Dependencies Check
check_and_fix('Composer dependencies', function() use ($plugin_path) {
    if (file_exists($plugin_path . '/vendor/autoload.php')) {
        return true;
    }
    return "Dependencies not installed";
}, function() use ($plugin_path) {
    // Try to install dependencies
    $original_dir = getcwd();
    chdir($plugin_path);
    
    $output = [];
    $return_var = 0;
    exec('composer install --no-dev --no-interaction 2>&1', $output, $return_var);
    
    chdir($original_dir);
    
    if ($return_var === 0 && file_exists($plugin_path . '/vendor/autoload.php')) {
        return true;
    }
    
    return "Failed to install dependencies: " . implode("\n", $output);
});

// 8. Database Connection Check
check_and_fix('Database connection', function() use ($plugin_path) {
    // Try to find LibreNMS path
    $possible_paths = [
        dirname($plugin_path, 3), // /opt/librenms
        '/opt/librenms',
        '/usr/local/librenms',
        getenv('LIBRENMS_PATH') ?: '',
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path . '/bootstrap/app.php')) {
            return true; // Found LibreNMS, assume DB is configured
        }
    }
    
    return "Cannot verify - LibreNMS not found";
}, null); // Can't auto-fix database issues

// 9. Log Directory Check
check_and_fix('Log directory', function() {
    $log_dirs = [
        '/var/log/librenms',
        '/opt/librenms/logs',
        '/tmp',
    ];
    
    foreach ($log_dirs as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return true;
        }
    }
    
    return "No writable log directory found";
}, function() {
    // Try to create log file in /tmp as fallback
    $test_file = '/tmp/weathermapng.log';
    if (touch($test_file)) {
        unlink($test_file);
        return true;
    }
    return "Cannot create log file";
});

// 10. Hook Files Check (LibreNMS integration)
check_and_fix('LibreNMS Hooks', function() use ($plugin_path) {
    $hooks_dir = $plugin_path . '/Hooks';
    if (!is_dir($hooks_dir)) {
        return "Hooks directory missing";
    }
    
    $required_hooks = ['Menu.php', 'DeviceOverview.php', 'PortTab.php'];
    $missing = [];
    
    foreach ($required_hooks as $hook) {
        if (!file_exists($hooks_dir . '/' . $hook)) {
            $missing[] = $hook;
        }
    }
    
    if (empty($missing)) {
        return true;
    }
    
    return "Missing hooks: " . implode(', ', $missing);
}, null); // Can't recreate complex hook files

// Summary
echo "\n";
echo "==========================================\n";
echo "📊 Verification Summary\n";
echo "==========================================\n";

if ($issues_found === 0) {
    output("All checks passed! ✨", 'success');
    echo "\n🎉 WeathermapNG is ready to use!\n";
    echo "Visit: https://your-librenms/plugins/weathermapng\n";
    exit(0);
} else {
    output("Found $issues_found issue(s)", 'warning');
    
    if ($FIX_MODE) {
        output("Fixed $issues_fixed issue(s)", 'success');
        $remaining = $issues_found - $issues_fixed;
        
        if ($remaining > 0) {
            output("$remaining issue(s) require manual intervention", 'error');
            echo "\n";
            echo "Manual fixes needed:\n";
            echo "1. Install missing PHP extensions (apt-get install php-gd php-mbstring)\n";
            echo "2. Ensure LibreNMS is properly installed\n";
            echo "3. Check file permissions (may need sudo)\n";
            echo "4. Enable plugin in LibreNMS web interface\n";
        }
    } else {
        echo "\n";
        echo "To attempt automatic fixes, run:\n";
        echo "  php verify.php --fix\n";
    }
    
    echo "\n";
    echo "For help, see: https://github.com/lance0/weathermapNG\n";
    
    exit($issues_found - $issues_fixed);
}
?>