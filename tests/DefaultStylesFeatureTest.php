<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class DefaultStylesFeatureTest extends TestCase
{
    public function test_editor_view_has_default_styles_panel(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $this->assertStringContainsString('Default Styles', $content);
        $this->assertStringContainsString('id="default-node-color"', $content);
        $this->assertStringContainsString('id="default-node-label-color"', $content);
        $this->assertStringContainsString('id="default-link-color"', $content);
        $this->assertStringContainsString('id="default-link-width"', $content);
        $this->assertStringContainsString('id="default-link-via-style"', $content);
    }

    public function test_editor_js_can_populate_and_read_defaults(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $this->assertStringContainsString('function populateDefaultStyles', $content);
        $this->assertStringContainsString('function getDefaultNodeStyle', $content);
        $this->assertStringContainsString('function getDefaultLinkStyle', $content);
    }

    public function test_editor_save_payload_includes_default_styles(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $this->assertStringContainsString('default_node_style: defaultNodeStyle', $content);
        $this->assertStringContainsString('default_link_style: defaultLinkStyle', $content);
    }

    public function test_embed_render_uses_default_styles(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/embed.blade.php');
        $this->assertStringContainsString('defaultNodeStyle', $content);
        $this->assertStringContainsString('defaultLinkStyle', $content);
        $this->assertStringContainsString('defaultNodeStyle.color', $content);
        $this->assertStringContainsString('defaultLinkStyle.color', $content);
        $this->assertStringContainsString('defaultLinkStyle.width', $content);
        $this->assertStringContainsString('defaultLinkStyle.via_style', $content);
    }
}
