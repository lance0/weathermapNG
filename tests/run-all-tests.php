<?php

/**
 * Comprehensive Test Runner for WeathermapNG
 *
 * This script runs all tests and provides a summary report.
 * Can be used in CI/CD pipelines or for local development.
 */

require_once __DIR__ . '/bootstrap.php';

echo "WeathermapNG Comprehensive Test Suite\n";
echo "=====================================\n\n";

// Test categories to run
$testCategories = [
    'Unit' => [
        'Models' => 'Unit/Models',
        'Services' => 'Unit/Services'
    ],
    'Feature' => [
        'Controllers' => 'Feature'
    ]
];

$results = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'errors' => 0,
    'skipped' => 0
];

foreach ($testCategories as $categoryName => $categories) {
    echo "{$categoryName} Tests:\n";
    echo str_repeat('-', strlen($categoryName) + 6) . "\n";

    foreach ($categories as $subCategoryName => $testPath) {
        echo "\n{$subCategoryName}:\n";

        $testFiles = glob(__DIR__ . '/' . $testPath . '/*Test.php');

        if (empty($testFiles)) {
            echo "  No test files found in {$testPath}\n";
            continue;
        }

        foreach ($testFiles as $testFile) {
            $results['total']++;
            $testName = basename($testFile, '.php');

            echo "  Running {$testName}... ";

            try {
                // Include and run the test
                require_once $testFile;

                // Get the class name
                $className = getClassNameFromFile($testFile);

                if (!$className || !class_exists($className)) {
                    echo "❌ Class not found\n";
                    $results['errors']++;
                    continue;
                }

                // Run the test class
                $testInstance = new $className();

                // Get all test methods
                $reflection = new ReflectionClass($className);
                $testMethods = array_filter($reflection->getMethods(), function($method) {
                    return strpos($method->getName(), 'test') === 0;
                });

                $passed = 0;
                $failed = 0;

                foreach ($testMethods as $method) {
                    try {
                        $testInstance->{$method->getName()}();
                        $passed++;
                    } catch (Exception $e) {
                        $failed++;
                    }
                }

                if ($failed === 0) {
                    echo "✅ {$passed} tests passed\n";
                    $results['passed'] += $passed;
                } else {
                    echo "⚠️  {$passed} passed, {$failed} failed\n";
                    $results['passed'] += $passed;
                    $results['failed'] += $failed;
                }

            } catch (Exception $e) {
                echo "❌ Error: " . $e->getMessage() . "\n";
                $results['errors']++;
            }
        }
    }

    echo "\n";
}

// Print final summary
echo "Final Test Results\n";
echo "==================\n";
echo "Total Tests:  {$results['total']}\n";
echo "Passed:       {$results['passed']}\n";
echo "Failed:       {$results['failed']}\n";
echo "Errors:       {$results['errors']}\n";
echo "Skipped:      {$results['skipped']}\n";

$successRate = $results['total'] > 0 ? round(($results['passed'] / $results['total']) * 100, 1) : 0;
echo "Success Rate: {$successRate}%\n\n";

if ($results['failed'] > 0 || $results['errors'] > 0) {
    echo "❌ Some tests failed. Please review the output above.\n";
    exit(1);
} else {
    echo "✅ All tests passed successfully!\n";
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