<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\WeathermapNG;

class WeathermapNGTest extends TestCase
{
    private WeathermapNG $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin = new WeathermapNG();
    }

    public function test_version_matches_expected(): void
    {
        $this->assertEquals('1.6.1', $this->plugin->getVersion());
    }

    public function test_get_info_returns_complete_structure(): void
    {
        $info = $this->plugin->getInfo();

        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('version', $info);
        $this->assertArrayHasKey('author', $info);
        $this->assertArrayHasKey('architecture', $info);
        $this->assertArrayHasKey('hooks', $info);
        $this->assertEquals('WeathermapNG', $info['name']);
        $this->assertEquals('hook-based', $info['architecture']);
    }

    public function test_get_info_hooks_contain_required_entries(): void
    {
        $info = $this->plugin->getInfo();
        $hooks = $info['hooks'];

        $this->assertArrayHasKey('menu', $hooks);
        $this->assertArrayHasKey('page', $hooks);
        $this->assertArrayHasKey('settings', $hooks);
    }

    public function test_hooks_info_returns_expected_keys(): void
    {
        $hooks = $this->plugin->getHooksInfo();

        $this->assertArrayHasKey('menu_hook', $hooks);
        $this->assertArrayHasKey('page_hook', $hooks);
        $this->assertArrayHasKey('settings_hook', $hooks);
        $this->assertArrayHasKey('location', $hooks);
        $this->assertArrayHasKey('architecture', $hooks);
    }

    public function test_check_requirements_returns_boolean_values(): void
    {
        $reqs = $this->plugin->checkRequirements();

        $this->assertIsBool($reqs['php']);
        $this->assertIsBool($reqs['gd']);
        $this->assertIsBool($reqs['json']);
        $this->assertIsBool($reqs['pdo']);
        $this->assertIsBool($reqs['mbstring']);
    }

    public function test_php_requirement_passes_on_current_version(): void
    {
        $reqs = $this->plugin->checkRequirements();
        $this->assertTrue($reqs['php'], 'PHP 8.0+ should be satisfied');
    }

    public function test_json_extension_is_available(): void
    {
        $reqs = $this->plugin->checkRequirements();
        $this->assertTrue($reqs['json'], 'JSON extension should always be available');
    }

    public function test_default_config_has_required_sections(): void
    {
        $config = $this->plugin->getDefaultConfig();

        $this->assertArrayHasKey('default_width', $config);
        $this->assertArrayHasKey('default_height', $config);
        $this->assertArrayHasKey('thresholds', $config);
        $this->assertArrayHasKey('colors', $config);
        $this->assertArrayHasKey('rendering', $config);
        $this->assertArrayHasKey('security', $config);
        $this->assertArrayHasKey('editor', $config);
    }

    public function test_default_dimensions_are_reasonable(): void
    {
        $config = $this->plugin->getDefaultConfig();

        $this->assertGreaterThanOrEqual(100, $config['default_width']);
        $this->assertLessThanOrEqual(4096, $config['default_width']);
        $this->assertGreaterThanOrEqual(100, $config['default_height']);
        $this->assertLessThanOrEqual(4096, $config['default_height']);
    }

    public function test_thresholds_are_ascending(): void
    {
        $config = $this->plugin->getDefaultConfig();
        $thresholds = $config['thresholds'];

        $this->assertCount(3, $thresholds);
        $this->assertLessThan($thresholds[1], $thresholds[0]);
        $this->assertLessThan($thresholds[2], $thresholds[1]);
    }

    public function test_colors_are_valid_hex(): void
    {
        $config = $this->plugin->getDefaultConfig();

        foreach ($config['colors'] as $name => $color) {
            $this->assertMatchesRegularExpression(
                '/^#[0-9a-fA-F]{6}$/',
                $color,
                "Color '{$name}' should be valid hex"
            );
        }
    }

    public function test_activate_returns_true(): void
    {
        $this->assertTrue($this->plugin->activate());
    }

    public function test_deactivate_returns_true(): void
    {
        $this->assertTrue($this->plugin->deactivate());
    }

    public function test_uninstall_returns_true(): void
    {
        $this->assertTrue($this->plugin->uninstall());
    }
}
