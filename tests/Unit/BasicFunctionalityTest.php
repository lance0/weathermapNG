<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests\Unit;

use LibreNMS\Plugins\WeathermapNG\Tests\TestCase;

class BasicFunctionalityTest extends TestCase
{
    /** @test */
    public function it_can_load_plugin_classes()
    {
        // Test that we can load the main plugin class
        $this->assertTrue(class_exists(\LibreNMS\Plugins\WeathermapNG\WeathermapNG::class));
    }

    /** @test */
    public function it_can_load_service_classes()
    {
        // Test that service classes can be loaded
        $this->assertTrue(class_exists(\LibreNMS\Plugins\WeathermapNG\Services\PortUtilService::class));
        $this->assertTrue(class_exists(\LibreNMS\Plugins\WeathermapNG\Services\DevicePortLookup::class));
    }

    /** @test */
    public function it_can_load_controller_classes()
    {
        // Test that controller classes can be loaded
        $this->assertTrue(class_exists(\LibreNMS\Plugins\WeathermapNG\Http\Controllers\MapController::class));
        $this->assertTrue(class_exists(\LibreNMS\Plugins\WeathermapNG\Http\Controllers\RenderController::class));
        $this->assertTrue(class_exists(\LibreNMS\Plugins\WeathermapNG\Http\Controllers\HealthController::class));
    }

    /** @test */
    public function it_can_load_rrd_classes()
    {
        // Test that RRD classes can be loaded
        $this->assertTrue(class_exists(\LibreNMS\Plugins\WeathermapNG\RRD\RRDTool::class));
        $this->assertTrue(class_exists(\LibreNMS\Plugins\WeathermapNG\RRD\LibreNMSAPI::class));
    }

    /** @test */
    public function it_has_required_plugin_structure()
    {
        // Check that essential files exist
        $requiredFiles = [
            'WeathermapNG.php',
            'routes.php',
            'composer.json',
            'README.md'
        ];

        foreach ($requiredFiles as $file) {
            $this->assertFileExists(__DIR__ . '/../../' . $file, "Required file {$file} is missing");
        }
    }

    /** @test */
    public function it_has_required_directories()
    {
        // Check that essential directories exist
        $requiredDirs = [
            'Http/Controllers',
            'Models',
            'Services',
            'Resources/views',
            'config',
            'lib/RRD'
        ];

        foreach ($requiredDirs as $dir) {
            $this->assertDirectoryExists(__DIR__ . '/../../' . $dir, "Required directory {$dir} is missing");
        }
    }

    /** @test */
    public function it_can_validate_configuration_structure()
    {
        // Test that configuration has expected structure
        $configFile = __DIR__ . '/../../config/weathermapng.php';

        $this->assertFileExists($configFile, 'Configuration file is missing');

        $config = include $configFile;

        $this->assertIsArray($config, 'Configuration should be an array');

        // Check for required configuration keys
        $requiredKeys = ['poll_interval', 'thresholds', 'rrd_base'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Configuration missing required key: {$key}");
        }
    }

    /** @test */
    public function it_can_validate_composer_structure()
    {
        // Test that composer.json has expected structure
        $composerFile = __DIR__ . '/../../composer.json';

        $this->assertFileExists($composerFile, 'Composer file is missing');

        $composer = json_decode(file_get_contents($composerFile), true);

        $this->assertIsArray($composer, 'Composer file should contain valid JSON');

        // Check for required composer keys
        $requiredKeys = ['name', 'description', 'require', 'autoload'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $composer, "Composer missing required key: {$key}");
        }
    }

    /** @test */
    public function it_can_validate_routes_structure()
    {
        // Test that routes.php exists and has basic structure
        $routesFile = __DIR__ . '/../../routes.php';

        $this->assertFileExists($routesFile, 'Routes file is missing');

        // Basic syntax check - if it doesn't parse, an exception will be thrown
        $routesContent = file_get_contents($routesFile);
        $this->assertStringContainsString('Route::', $routesContent, 'Routes file should contain Route definitions');
    }

    /** @test */
    public function it_has_test_infrastructure()
    {
        // Test that test infrastructure is in place
        $testFiles = [
            'TestCase.php',
            'bootstrap.php',
            'phpunit.xml'
        ];

        foreach ($testFiles as $file) {
            $this->assertFileExists(__DIR__ . '/../' . $file, "Test infrastructure file {$file} is missing");
        }
    }

    /** @test */
    public function it_can_validate_plugin_interface()
    {
        // Test that the main plugin class implements expected interface
        $pluginClass = \LibreNMS\Plugins\WeathermapNG\WeathermapNG::class;

        $reflection = new \ReflectionClass($pluginClass);

        // Check that it has the expected methods
        $expectedMethods = ['activate', 'deactivate', 'uninstall', 'getVersion'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "Plugin missing required method: {$method}");
        }
    }
}