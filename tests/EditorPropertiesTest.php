<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

class EditorPropertiesTest extends TestCase
{
    public function test_node_label_input_updates_canvas_and_nodes_list(): void
    {
        $content = editor_source();
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
        $content = editor_source();
        $this->assertStringContainsString('id="link-bandwidth-value"', $content);
        $this->assertStringContainsString('id="link-bandwidth-unit"', $content);
        $this->assertStringContainsString('<option value="Mbps">Mbps</option>', $content);
        $this->assertStringContainsString('<option value="MBps">MBps</option>', $content);
    }

    public function test_link_bandwidth_conversion_helpers_exist(): void
    {
        $content = editor_source();
        $this->assertStringContainsString('function bandwidthInputsToBps', $content);
        $this->assertStringContainsString('function setBandwidthInputsFromBps', $content);
        $this->assertStringContainsString("MBps: 8 * 1000 * 1000", $content);
    }

    public function test_link_save_renders_canvas_and_links_list(): void
    {
        $content = editor_source();
        $saveBlock = substr($content, strpos($content, 'function saveLink()'), 1200);
        $this->assertStringContainsString('renderEditor()', $saveBlock);
        $this->assertStringContainsString('renderLinksList()', $saveBlock);
    }

    public function test_node_property_inputs_mark_unsaved(): void
    {
        $content = editor_source();
        $labelBlock = substr($content, strpos($content, 'label.oninput = function'), 250);
        $this->assertStringContainsString('markUnsaved()', $labelBlock);

        // Device selection via autocomplete now triggers markUnsaved in the select callback
        $devBlock = substr($content, strpos($content, 'if (devHidden) devHidden.value = device.device_id'), 300);
        $this->assertStringContainsString('markUnsaved()', $devBlock);
    }

    public function test_save_existing_map_payload_includes_name(): void
    {
        $content = editor_source();
        $existingBlockStart = strpos($content, "WMNGLoading.show('Saving map...')");
        $this->assertNotFalse($existingBlockStart);
        $saveBlock = substr($content, $existingBlockStart, 900);
        $this->assertStringContainsString('name: mapName', $saveBlock);
    }

    public function test_default_style_panel_wires_live_preview(): void
    {
        $content = editor_source();
        $this->assertStringContainsString('function initDefaultStyleListeners()', $content);
        $this->assertStringContainsString("nodeColor.addEventListener('input'", $content);
        $this->assertStringContainsString("linkWidth.addEventListener('input'", $content);
        $this->assertStringContainsString("mapName.addEventListener('input'", $content);
    }

    public function test_map_dimensions_mark_unsaved_on_input(): void
    {
        $content = editor_source();
        $resizeBlock = substr($content, strpos($content, 'function initCanvasResizeValidation()'), 1500);
        $this->assertStringContainsString("widthInput.addEventListener('input'", $resizeBlock);
        $this->assertStringContainsString("heightInput.addEventListener('input'", $resizeBlock);
        $this->assertStringContainsString('markUnsaved()', $resizeBlock);
    }

    public function test_node_style_controls_update_anchor_and_push_undo(): void
    {
        $content = editor_source();

        $this->assertStringContainsString('function populateNodeStyleControls(node)', $content);
        $this->assertStringContainsString("color.oninput = function () { updateNodeStyle(node, 'color', this.value); };", $content);
        $this->assertStringContainsString("labelColor.oninput = function () { updateNodeStyle(node, 'label_color', this.value); };", $content);
        $this->assertStringContainsString("updateNodeStyle(node, 'shape', this.value || null)", $content);
        $this->assertStringContainsString("size.oninput = function () { updateNodeSize(node, this.value); };", $content);

        $updateBlock = substr($content, strpos($content, 'function updateNodeStyle(node, key, value)'), 600);
        foreach (['saveState()', 'markUnsaved()', 'renderEditor()', 'renderNodesList()'] as $call) {
            $this->assertStringContainsString($call, $updateBlock, "style edits must call $call");
        }
        $this->assertStringContainsString('populateNodeStyleControls(node);', $content);
    }

    public function test_apply_style_to_selected_copies_full_style_in_one_undo_step(): void
    {
        $content = editor_source();

        $applyBlock = substr($content, strpos($content, 'function applyStyleToSelected()'), 2400);
        $this->assertStringContainsString('saveState()', $applyBlock);
        $this->assertStringContainsString('NODE_STYLE_META_KEYS.forEach', $applyBlock);
        $this->assertStringContainsString('nodeMetaObject(anchor)', $applyBlock);
        $this->assertStringContainsString("delete meta[key]", $applyBlock);
        $this->assertStringContainsString('WMNGToast.success', $applyBlock);
        $this->assertStringContainsString('S.selectedNodes.length <= 1', $applyBlock);
    }
}
