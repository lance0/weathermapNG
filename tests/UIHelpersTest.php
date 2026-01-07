<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class UIHelpersTest extends TestCase
{
    public function test_ui_helpers_js_file_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../resources/js/ui-helpers.js');
    }

    public function test_ui_helpers_file_contains_toast_class(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/js/ui-helpers.js');
        $this->assertStringContainsString('class WMNGToast', $content);
    }

    public function test_ui_helpers_file_contains_loading_class(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/js/ui-helpers.js');
        $this->assertStringContainsString('class WMNGLoading', $content);
    }

    public function test_ui_helpers_file_contains_a11y_class(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/js/ui-helpers.js');
        $this->assertStringContainsString('class WMNGA11y', $content);
    }

    public function test_ui_helpers_initializes_global_classes(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/js/ui-helpers.js');
        $this->assertStringContainsString('window.WMNGToast = new WMNGToast()', $content);
        $this->assertStringContainsString('window.WMNGLoading = new WMNGLoading()', $content);
        $this->assertStringContainsString('window.WMNGA11y = WMNGA11y;', $content);
    }

    public function test_ui_helpers_has_show_methods(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/js/ui-helpers.js');
        $this->assertStringContainsString('show(message, type', $content);
        $this->assertStringContainsString('show(message, options', $content);
        $this->assertStringContainsString('success(message)', $content);
        $this->assertStringContainsString('error(message)', $content);
    }
}

