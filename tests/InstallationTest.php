<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\WeathermapNG;
use PHPUnit\Framework\TestCase;

class InstallationTest extends TestCase
{
    public function testPluginCanBeInstantiated()
    {
        $plugin = new WeathermapNG();
        $this->assertInstanceOf(WeathermapNG::class, $plugin);
    }

    public function testPluginHasRequiredMethods()
    {
        $plugin = new WeathermapNG();

        $this->assertTrue(method_exists($plugin, 'activate'));
        $this->assertTrue(method_exists($plugin, 'deactivate'));
        $this->assertTrue(method_exists($plugin, 'uninstall'));
        $this->assertTrue(method_exists($plugin, 'getVersion'));
        $this->assertTrue(method_exists($plugin, 'getInfo'));
    }

    public function testPluginInfoIsCorrect()
    {
        $plugin = new WeathermapNG();
        $info = $plugin->getInfo();

        $this->assertEquals('WeathermapNG', $info['name']);
        $this->assertEquals('Modern interactive network weathermap for LibreNMS', $info['description']);
        $this->assertEquals('1.1.0', $info['version']);
        $this->assertEquals('LibreNMS Community', $info['author']);
    }

    public function testRequirementsCheckMethodExists()
    {
        $plugin = new WeathermapNG();

        // Use reflection to test private method
        $reflection = new \ReflectionClass($plugin);
        $method = $reflection->getMethod('checkRequirements');
        $method->setAccessible(true);

        $requirements = $method->invoke($plugin);
        $this->assertIsArray($requirements);
        $this->assertArrayHasKey('php', $requirements);
        $this->assertArrayHasKey('gd', $requirements);
    }

    public function testDefaultConfigStructure()
    {
        $plugin = new WeathermapNG();

        // Use reflection to test private method
        $reflection = new \ReflectionClass($plugin);
        $method = $reflection->getMethod('getDefaultConfig');
        $method->setAccessible(true);

        $config = $method->invoke($plugin);
        $this->assertIsArray($config);
        $this->assertArrayHasKey('default_width', $config);
        $this->assertArrayHasKey('default_height', $config);
        $this->assertArrayHasKey('colors', $config);
        $this->assertArrayHasKey('rendering', $config);
    }
}