<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class EditorPropertiesTest extends TestCase
{
    public function test_node_label_input_updates_canvas_and_nodes_list(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $this->assertStringContainsString('id="node-prop-label"', $content);
        $this->assertStringContainsString('label.oninput = function', $content);
        $this->assertStringContainsString('node.label = this.value', $content);
        // Should trigger immediate re-renders after label/device/interface changes.
        $inputsBlock = substr($content, strpos($content, 'label.oninput = function'), 300);
        $this->assertStringContainsString('renderEditor()', $inputsBlock);
        $this->assertStringContainsString('renderNodesList()', $inputsBlock);
    }

    public function test_link_bandwidth_has_value_and_unit_inputs(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $this->assertStringContainsString('id="link-bandwidth-value"', $content);
        $this->assertStringContainsString('id="link-bandwidth-unit"', $content);
        $this->assertStringContainsString('<option value="Mbps">Mbps</option>', $content);
        $this->assertStringContainsString('<option value="MBps">MBps</option>', $content);
    }

    public function test_link_bandwidth_conversion_helpers_exist(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $this->assertStringContainsString('function bandwidthInputsToBps', $content);
        $this->assertStringContainsString('function setBandwidthInputsFromBps', $content);
        $this->assertStringContainsString("MBps: 8 * 1000 * 1000", $content);
    }

    public function test_link_save_renders_canvas_and_links_list(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $saveBlock = substr($content, strpos($content, 'function saveLink()'), 1200);
        $this->assertStringContainsString('renderEditor()', $saveBlock);
        $this->assertStringContainsString('renderLinksList()', $saveBlock);
    }
}
