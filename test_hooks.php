<?php
/**
 * Test script to verify hook loading
 * Run with: php test_hooks.php
 */

echo "Testing WeathermapNG Hook Loading\n";
echo "==================================\n\n";

$hookDir = __DIR__ . '/app/Plugins/WeathermapNG';
$hooks = ['Menu.php', 'Page.php', 'Settings.php'];

foreach ($hooks as $hookFile) {
    $fullPath = $hookDir . '/' . $hookFile;

    echo "Testing $hookFile...\n";

    if (!file_exists($fullPath)) {
        echo "❌ File not found: $fullPath\n\n";
        continue;
    }

    try {
        // Include the hook file
        require_once $fullPath;

        // Extract class name from file
        $className = 'App\\Plugins\\WeathermapNG\\' . pathinfo($hookFile, PATHINFO_FILENAME);

        if (class_exists($className)) {
            echo "✅ Class loaded: $className\n";

            // Try to instantiate
            $instance = new $className();
            echo "✅ Class instantiated\n";

            // Check for required methods
            if (method_exists($instance, 'data')) {
                echo "✅ Has data() method\n";
            } else {
                echo "⚠️  Missing data() method\n";
            }

            if (method_exists($instance, 'authorize')) {
                echo "✅ Has authorize() method\n";
            } else {
                echo "⚠️  Missing authorize() method\n";
            }

            // Test data method if it exists
            if (method_exists($instance, 'data')) {
                try {
                    $data = $instance->data();
                    echo "✅ data() method executed successfully\n";
                    if (is_array($data)) {
                        echo "✅ data() returned array with " . count($data) . " items\n";
                    } else {
                        echo "⚠️  data() did not return array\n";
                    }
                } catch (Exception $e) {
                    echo "❌ data() method failed: " . $e->getMessage() . "\n";
                }
            }

        } else {
            echo "❌ Class not found: $className\n";
        }

    } catch (Exception $e) {
        echo "❌ Error loading hook: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "Hook testing completed!\n";
echo "If all hooks show ✅ then they should work in LibreNMS.\n";
?>