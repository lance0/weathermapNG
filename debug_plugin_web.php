<?php
echo "<h2>WeathermapNG Web Context Debug</h2>";
echo "<pre>";

// Check current user context
echo "Web server: Nginx\n";
echo "PHP user: " . get_current_user() . "\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "Working directory: " . getcwd() . "\n";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n\n";

// Check plugin directory access
$pluginPath = __DIR__ . '/html/plugins/WeathermapNG';
echo "Plugin path: $pluginPath\n";
echo "Plugin directory exists: " . (is_dir($pluginPath) ? 'YES' : 'NO') . "\n";
echo "Plugin directory readable: " . (is_readable($pluginPath) ? 'YES' : 'NO') . "\n\n";

// Check hook directory
$hookPath = $pluginPath . '/app/Plugins/WeathermapNG';
echo "Hook path: $hookPath\n";
echo "Hook directory exists: " . (is_dir($hookPath) ? 'YES' : 'NO') . "\n";
echo "Hook directory readable: " . (is_readable($hookPath) ? 'YES' : 'NO') . "\n\n";

// List hook files
if (is_dir($hookPath)) {
    $hooks = glob($hookPath . '/*.php');
    echo "Hook files found: " . count($hooks) . "\n";
    foreach ($hooks as $hook) {
        echo "  - " . basename($hook) . "\n";
        echo "    Readable: " . (is_readable($hook) ? 'YES' : 'NO') . "\n";
    }
    echo "\n";

    // Try to load hooks in web context
    echo "Testing hook loading in web context...\n";
    foreach ($hooks as $hook) {
        $className = 'App\\Plugins\\WeathermapNG\\' . basename($hook, '.php');
        echo "Loading " . basename($hook) . " ($className)... ";

        try {
            require_once $hook;
            if (class_exists($className)) {
                echo "SUCCESS\n";
                $instance = new $className();
                echo "  Instantiated: YES\n";

                // Test methods
                if (method_exists($instance, 'data')) {
                    echo "  Has data() method: YES\n";
                }
                if (method_exists($instance, 'settings')) {
                    echo "  Has settings() method: YES\n";
                }
                if (method_exists($instance, 'authorize')) {
                    echo "  Has authorize() method: YES\n";
                }
            } else {
                echo "FAILED - Class not found\n";
            }
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
}

// Check LibreNMS plugin system
echo "Checking LibreNMS plugin system...\n";
if (class_exists('LibreNMS\\Plugins')) {
    echo "LibreNMS Plugins class: FOUND\n";
    try {
        $plugins = LibreNMS\Plugins::getInstance();
        echo "Plugin instance: CREATED\n";

        $allPlugins = $plugins->getPlugins();
        echo "Total plugins loaded: " . count($allPlugins) . "\n";

        $weathermapFound = false;
        foreach ($allPlugins as $name => $plugin) {
            if (stripos($name, 'weathermap') !== false) {
                echo "WeathermapNG FOUND: $name\n";
                $weathermapFound = true;
            }
        }

        if (!$weathermapFound) {
            echo "WeathermapNG NOT found in loaded plugins\n";
            echo "Available plugins:\n";
            foreach (array_keys($allPlugins) as $name) {
                echo "  - $name\n";
            }
        }

    } catch (Exception $e) {
        echo "Plugin system error: " . $e->getMessage() . "\n";
    }
} else {
    echo "LibreNMS Plugins class: NOT FOUND\n";
}

// Check database
echo "\nChecking database...\n";
try {
    // Try to connect to database
    if (function_exists('config') && config('database.default') === 'mysql') {
        echo "Database configured: YES\n";

        // Try a simple query
        $pdo = new PDO(
            "mysql:host=" . config('database.connections.mysql.host') . ";dbname=" . config('database.connections.mysql.database'),
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password')
        );

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM plugins WHERE plugin_name = 'WeathermapNG'");
        $result = $stmt->fetch();
        echo "WeathermapNG in database: " . ($result['count'] > 0 ? 'YES' : 'NO') . "\n";

        if ($result['count'] > 0) {
            $stmt = $pdo->query("SELECT plugin_active FROM plugins WHERE plugin_name = 'WeathermapNG'");
            $result = $stmt->fetch();
            echo "Plugin active: " . ($result['plugin_active'] ? 'YES' : 'NO') . "\n";
        }

    } else {
        echo "Database configuration: UNKNOWN\n";
    }
} catch (Exception $e) {
    echo "Database check error: " . $e->getMessage() . "\n";
}

echo "\nDebug complete.";
echo "</pre>";
?>