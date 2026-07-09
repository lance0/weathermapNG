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

    public function test_node_property_inputs_mark_unsaved(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $labelBlock = substr($content, strpos($content, 'label.oninput = function'), 250);
        $this->assertStringContainsString('markUnsaved()', $labelBlock);

        $devBlock = substr($content, strpos($content, 'devSel.onchange = function'), 250);
        $this->assertStringContainsString('markUnsaved()', $devBlock);
    }

    public function test_save_existing_map_payload_includes_name(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $existingBlockStart = strpos($content, "WMNGLoading.show('Saving map...')");
        $this->assertNotFalse($existingBlockStart);
        $saveBlock = substr($content, $existingBlockStart, 900);
        $this->assertStringContainsString('name: mapName', $saveBlock);
    }

    public function test_default_style_panel_wires_live_preview(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $this->assertStringContainsString('function initDefaultStyleListeners()', $content);
        $this->assertStringContainsString("nodeColor.addEventListener('input'", $content);
        $this->assertStringContainsString("linkWidth.addEventListener('input'", $content);
        $this->assertStringContainsString("mapName.addEventListener('input'", $content);
    }

    public function test_map_dimensions_mark_unsaved_on_input(): void
    {
        $content = file_get_contents(__DIR__ . '/../resources/views/editor.blade.php');
        $resizeBlock = substr($content, strpos($content, 'function initCanvasResizeValidation()'), 1500);
        $this->assertStringContainsString("widthInput.addEventListener('input'", $resizeBlock);
        $this->assertStringContainsString("heightInput.addEventListener('input'", $resizeBlock);
        $this->assertStringContainsString('markUnsaved()', $resizeBlock);
    }
}
