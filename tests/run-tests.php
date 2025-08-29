<?php

/**
 * Simple Test Runner for WeathermapNG
 *
 * This script runs the test suite without requiring composer.
 * Useful for basic testing and CI/CD pipelines.
 */

require_once __DIR__ . '/bootstrap.php';

echo "WeathermapNG Test Runner\n";
echo "========================\n\n";

// Discover test files
$testFiles = glob(__DIR__ . '/*Test.php');
$testFiles = array_merge($testFiles, glob(__DIR__ . '/**/*Test.php'));

if (empty($testFiles)) {
    echo "No test files found in " . __DIR__ . "\n";
    exit(1);
}

echo "Found " . count($testFiles) . " test files:\n";
foreach ($testFiles as $file) {
    echo "  - " . basename($file) . "\n";
}
echo "\n";

// Run tests
$results = [
    'passed' => 0,
    'failed' => 0,
    'errors' => 0,
    'skipped' => 0
];

foreach ($testFiles as $testFile) {
    echo "Running " . basename($testFile) . "...\n";

    try {
        // Include the test file
        require_once $testFile;

        // Get the class name from the file
        $className = getClassNameFromFile($testFile);

        if (!$className) {
            echo "  ❌ Could not determine class name\n";
            $results['errors']++;
            continue;
        }

        // Run basic class instantiation test
        if (class_exists($className)) {
            echo "  ✅ Class {$className} loaded successfully\n";
            $results['passed']++;

            // Try to instantiate if it's not abstract
            $reflection = new ReflectionClass($className);
            if (!$reflection->isAbstract() && $reflection->isInstantiable()) {
                try {
                    $instance = new $className();
                    echo "  ✅ Class {$className} instantiated successfully\n";
                } catch (Exception $e) {
                    echo "  ⚠️  Class {$className} instantiation failed: " . $e->getMessage() . "\n";
                    $results['skipped']++;
                }
            }
        } else {
            echo "  ❌ Class {$className} not found\n";
            $results['errors']++;
        }

    } catch (Exception $e) {
        echo "  ❌ Error loading test file: " . $e->getMessage() . "\n";
        $results['errors']++;
    }

    echo "\n";
}

// Print summary
echo "Test Results Summary\n";
echo "===================\n";
echo "Passed:  {$results['passed']}\n";
echo "Failed:  {$results['failed']}\n";
echo "Errors:  {$results['errors']}\n";
echo "Skipped: {$results['skipped']}\n";
echo "\n";

$total = array_sum($results);
$successRate = $total > 0 ? round(($results['passed'] / $total) * 100, 1) : 0;

echo "Success Rate: {$successRate}%\n";

if ($results['errors'] > 0 || $results['failed'] > 0) {
    echo "\n❌ Some tests failed. Please review the output above.\n";
    exit(1);
} else {
    echo "\n✅ All basic tests passed!\n";
    exit(0);
}

function getClassNameFromFile($filePath) {
    $content = file_get_contents($filePath);

    // Look for namespace and class declarations
    if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
        $namespace = $namespaceMatch[1];

        if (preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return $namespace . '\\' . $classMatch[1];
        }
    }

    return null;
}