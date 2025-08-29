<?php

// Test bootstrap file for WeathermapNG
// This file sets up the testing environment

// Define test constants
define('TESTING', true);
define('WP_ROOT', dirname(__DIR__));

// Set up basic autoloading for our classes
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'LibreNMS\\Plugins\\WeathermapNG\\';
    $baseDir = WP_ROOT . '/';

    if (strpos($class, $prefix) === 0) {
        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Set up error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Mock basic Laravel functions if they don't exist
if (!function_exists('config')) {
    function config($key, $default = null) {
        static $config = [];

        if (strpos($key, '.') !== false) {
            list($section, $item) = explode('.', $key, 2);
            return $config[$section][$item] ?? $default;
        }

        return $config[$key] ?? $default;
    }
}

if (!function_exists('app')) {
    function app($abstract = null) {
        static $container = [];

        if ($abstract) {
            return $container[$abstract] ?? null;
        }

        return (object) $container;
    }
}

if (!function_exists('env')) {
    function env($key, $default = null) {
        return getenv($key) ?: $default;
    }
}

// Set up test database connection
if (!function_exists('dbFetchRow')) {
    function dbFetchRow($query, $params = []) {
        // Mock database function for tests
        return null;
    }
}

if (!function_exists('dbFetchRows')) {
    function dbFetchRows($query, $params = []) {
        // Mock database function for tests
        return [];
    }
}

// Load test configuration
$configFile = WP_ROOT . '/config/weathermapng.php';
if (file_exists($configFile)) {
    $testConfig = include $configFile;
    // Override test-specific settings
    $testConfig['enable_local_rrd'] = false;
    $testConfig['enable_api_fallback'] = false;
    $testConfig['cache_ttl'] = 0;

    // Make config available globally
    foreach ($testConfig as $key => $value) {
        $GLOBALS['weathermapng_config'][$key] = $value;
    }
}

// Helper function to get test config
function getTestConfig($key = null, $default = null) {
    if ($key === null) {
        return $GLOBALS['weathermapng_config'] ?? [];
    }

    if (strpos($key, '.') !== false) {
        list($section, $item) = explode('.', $key, 2);
        return $GLOBALS['weathermapng_config'][$section][$item] ?? $default;
    }

    return $GLOBALS['weathermapng_config'][$key] ?? $default;
}