<?php

namespace Illuminate\Foundation\Http {
    // Stub the FormRequest base class: illuminate/foundation is not installed
    // in the standalone PHPUnit bootstrap, but SaveMapRequest's pure
    // sanitizeNodeMeta() helper is otherwise testable through reflection.
    if (!class_exists(FormRequest::class)) {
        class FormRequest
        {
        }
    }
}

namespace LibreNMS\Plugins\WeathermapNG\Tests {

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Http\Requests\SaveMapRequest;

/**
 * Per-node style contract (LAN-269 remainder):
 *
 *  - server: meta style allowlist validation + sanitize that keeps unknown
 *    meta keys intact and drops invalid style values;
 *  - editor: payloads merge the loaded meta, ctrl/delete handlers are
 *    guarded, and drawNode honors meta.color/label_color/shape/size.
 */
class EditorStyleTest extends TestCase
{
    private function invokeSanitizeNodeMeta(array $meta): array
    {
        $method = new \ReflectionMethod(SaveMapRequest::class, 'sanitizeNodeMeta');

        return $method->invoke(null, $meta);
    }

    private function sanitizePayload(array $data): array
    {
        $request = (new \ReflectionClass(SaveMapRequest::class))->newInstanceWithoutConstructor();

        return $request->sanitize($data);
    }

    public function test_save_request_validation_allows_style_meta_keys(): void
    {
        $content = file_get_contents(__DIR__ . '/../src/Http/Requests/SaveMapRequest.php');

        $this->assertStringContainsString("'nodes.*.meta' => 'nullable|array'", $content);
        $this->assertStringContainsString("'nodes.*.meta.color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/'", $content);
        $this->assertStringContainsString("'nodes.*.meta.label_color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/'", $content);
        $this->assertStringContainsString("'nodes.*.meta.shape' => 'nullable|string|in:circle,square,diamond,triangle'", $content);
        $this->assertStringContainsString("'nodes.*.meta.size' => 'nullable|numeric|min:6|max:40'", $content);
    }

    public function test_sanitize_node_meta_keeps_style_and_unknown_keys(): void
    {
        $meta = [
            'interface_id' => 12,
            'sub_map_id' => 4,
            'tooltip' => 'core link',
            'color' => '#FF0000',
            'label_color' => ' #00ff00 ',
            'shape' => 'diamond',
            'size' => '24',
        ];

        $result = $this->invokeSanitizeNodeMeta($meta);

        $this->assertSame(12, $result['interface_id'], 'unknown keys are preserved');
        $this->assertSame(4, $result['sub_map_id']);
        $this->assertSame('core link', $result['tooltip']);
        $this->assertSame('#ff0000', $result['color']);
        $this->assertSame('#00ff00', $result['label_color']);
        $this->assertSame('diamond', $result['shape']);
        $this->assertSame(24.0, $result['size'], 'size is cast to float');
    }

    public function test_sanitize_node_meta_drops_invalid_style_values(): void
    {
        $meta = [
            'interface_id' => 3,
            'color' => 'red',
            'label_color' => '#12345',
            'shape' => 'hexagon',
            'size' => 41,
        ];

        $result = $this->invokeSanitizeNodeMeta($meta);

        $this->assertSame(3, $result['interface_id']);
        foreach (['color', 'label_color', 'shape', 'size'] as $key) {
            $this->assertArrayNotHasKey($key, $result, "$key override must be dropped when invalid");
        }
    }

    public function test_sanitize_node_meta_strips_tags_and_clamps_size_range(): void
    {
        $result = $this->invokeSanitizeNodeMeta([
            'color' => '<b>#abcdef</b>',
            'shape' => ' Square ',
            'size' => 5,
        ]);

        $this->assertSame('#abcdef', $result['color']);
        $this->assertSame('square', $result['shape']);
        $this->assertArrayNotHasKey('size', $result, 'size below 6 is dropped');
    }

    public function test_sanitize_payload_applies_meta_allowlist_per_node(): void
    {
        $data = $this->sanitizePayload([
            'nodes' => [
                [
                    'label' => 'Core',
                    'x' => 10,
                    'y' => 20,
                    'meta' => [
                        'interface_id' => 5,
                        'tooltip' => 'keep me',
                        'color' => '#112233',
                        'shape' => 'triangle',
                        'bogus' => 'kept',
                    ],
                ],
            ],
            'links' => [],
        ]);

        $this->assertSame(5, $data['nodes'][0]['meta']['interface_id']);
        $this->assertSame('keep me', $data['nodes'][0]['meta']['tooltip']);
        $this->assertSame('kept', $data['nodes'][0]['meta']['bogus']);
        $this->assertSame('#112233', $data['nodes'][0]['meta']['color']);
        $this->assertSame('triangle', $data['nodes'][0]['meta']['shape']);
    }

    public function test_editor_save_and_export_payloads_merge_node_meta(): void
    {
        $content = editor_source();

        // Empty PHP arrays decode as [] ; every payload path coerces that (and
        // any other non-object) to a plain object before spreading style keys.
        $this->assertStringContainsString("meta: { ...((n.meta && typeof n.meta === 'object' && !Array.isArray(n.meta)) ? n.meta : {}), interface_id: n.interfaceId || null }", $content);
        $this->assertStringContainsString('meta: { ...nodeMetaObject(S.selectedNode), interface_id: ifaceId', $content);
        $this->assertStringContainsString('meta: JSON.parse(JSON.stringify(nodeMetaObject(S.selectedNode)))', $content);
        $this->assertStringContainsString("meta: (node.meta && typeof node.meta === 'object' && !Array.isArray(node.meta)) ? node.meta : {}", $content);
        $this->assertStringContainsString('function nodeMetaObject(node)', $content);
        $this->assertStringNotContainsString('meta: { interface_id: n.interfaceId || null }', $content);
    }

    public function test_editor_draw_node_honors_meta_style(): void
    {
        $content = editor_source();

        $this->assertStringContainsString('function getNodeStyle(node)', $content);
        $this->assertStringContainsString("NODE_STYLE_SHAPES = ['circle', 'square', 'diamond', 'triangle']", $content);
        $this->assertStringContainsString('traceNodeShape(ctx, node, radius, style.shape)', $content);
        $this->assertStringContainsString('ctx.fillStyle = style.color;', $content);
        $this->assertStringContainsString('ctx.fillStyle = style.labelColor;', $content);
        // Highlight wins while the node is selected/link-start.
        $selectionBlock = substr($content, strpos($content, 'function drawNode(node)'), 700);
        $this->assertStringContainsString('ctx.fillStyle = \'#0d6efd\'', $selectionBlock);
    }

    public function test_editor_ctrl_a_and_bulk_delete_are_guarded(): void
    {
        $content = editor_source();

        $this->assertStringContainsString('function isEditorModalOpen()', $content);
        $this->assertStringContainsString("if (ctrl && key === 'a')", $content);
        $this->assertStringContainsString('selectAllNodes()', $content);
        $this->assertStringContainsString('function selectAllNodes()', $content);
        $this->assertStringContainsString('bulkDeleteSelected();', $content);
        $this->assertStringContainsString('target.isContentEditable', $content);

        $keydown = substr($content, strpos($content, 'function handleKeyDown(event)'), 1600);
        $this->assertStringContainsString('isEditorModalOpen()', $keydown, 'shortcuts must bail out while a modal is open');
        $this->assertStringContainsString('S.selectedNodes.length > 1', $keydown);
    }

    public function test_editor_style_controls_and_bulk_apply_button_exist(): void
    {
        $content = editor_source();

        foreach (['node-prop-color', 'node-prop-label-color', 'node-prop-shape', 'node-prop-size'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $content);
        }
        $this->assertStringContainsString('<option value="circle">Circle</option>', $content);
        $this->assertStringContainsString('<option value="square">Square</option>', $content);
        $this->assertStringContainsString('<option value="diamond">Diamond</option>', $content);
        $this->assertStringContainsString('<option value="triangle">Triangle</option>', $content);
        $this->assertStringContainsString('id="apply-style-selected-btn"', $content);
        $this->assertStringContainsString('onclick="applyStyleToSelected()"', $content);
        $this->assertStringContainsString('function applyStyleToSelected()', $content);
        $this->assertStringContainsString('function updateApplyStyleButton()', $content);
        $this->assertStringContainsString('btn.disabled = count <= 1 || !S.selectedNode;', $content);
    }
}

}
