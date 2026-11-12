<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use LibreNMS\Plugins\WeathermapNG\Services\NodeMetaNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Per-node style normalization (LAN-269 follow-up).
 *
 * Style keys live flat in wmng_nodes.meta and reach storage from saves,
 * imports, node PATCH and version restore. Invalid values must be dropped on
 * every path (a stored "red" used to fail the next whole-map save) while
 * unknown metadata survives untouched.
 */
class NodeMetaNormalizerTest extends TestCase
{
    public function test_keeps_unknown_keys_and_valid_style(): void
    {
        $meta = [
            'interface_id' => 7,
            'sub_map_id' => 3,
            'tooltip' => 'edge link',
            'color' => '#AABBCC',
            'label_color' => '#001122',
            'shape' => 'diamond',
            'size' => 18,
        ];

        $out = NodeMetaNormalizer::sanitize($meta);

        $this->assertSame(7, $out['interface_id']);
        $this->assertSame(3, $out['sub_map_id']);
        $this->assertSame('edge link', $out['tooltip']);
        $this->assertSame('#aabbcc', $out['color'], 'hex is lowercased');
        $this->assertSame('#001122', $out['label_color']);
        $this->assertSame('diamond', $out['shape']);
        $this->assertSame(18.0, $out['size']);
    }

    public function test_drops_invalid_style_values(): void
    {
        $meta = [
            'interface_id' => null,
            'color' => 'red',
            'label_color' => '#fff',
            'shape' => 'hexagon',
            'size' => 45,
        ];

        $out = NodeMetaNormalizer::sanitize($meta);

        $this->assertSame(['interface_id' => null], $out, 'only the non-style key survives');
    }

    public function test_accepts_boundaries_and_numeric_strings(): void
    {
        $this->assertSame(6.0, NodeMetaNormalizer::sanitize(['size' => 6])['size']);
        $this->assertSame(40.0, NodeMetaNormalizer::sanitize(['size' => '40'])['size']);
        $this->assertArrayNotHasKey('size', NodeMetaNormalizer::sanitize(['size' => 5.9]));
        $this->assertArrayNotHasKey('size', NodeMetaNormalizer::sanitize(['size' => 40.1]));
        $this->assertArrayNotHasKey('size', NodeMetaNormalizer::sanitize(['size' => true]));
        $this->assertArrayNotHasKey('size', NodeMetaNormalizer::sanitize(['size' => 'auto']));
    }

    public function test_trims_and_strips_tag_markup(): void
    {
        $this->assertSame('#00ff00', NodeMetaNormalizer::sanitize(['color' => '  #00FF00  '])['color']);
        $out = NodeMetaNormalizer::sanitize(['shape' => '<b>Circle</b>']);
        $this->assertSame('circle', $out['shape']);
    }

    public function test_handles_empty_and_list_shaped_meta(): void
    {
        $this->assertSame([], NodeMetaNormalizer::sanitize([]));
        // A list-shaped meta has no named style keys; entries are unknown keys
        // and survive verbatim (the editor coerces the value to an object).
        $this->assertSame([0 => '#ff0000', 1 => 'circle'], NodeMetaNormalizer::sanitize([0 => '#ff0000', 1 => 'circle']));
    }

    public function test_save_request_normalizes_meta_before_validation(): void
    {
        // Rules reject non-hex colors, so invalid stored/imported values must be
        // repaired in prepareForValidation() or the whole map save 422s.
        $content = file_get_contents(__DIR__ . '/../src/Http/Requests/SaveMapRequest.php');

        $this->assertStringContainsString('use LibreNMS\\Plugins\\WeathermapNG\\Services\\NodeMetaNormalizer;', $content);
        $this->assertStringContainsString('return NodeMetaNormalizer::sanitize($meta);', $content);
        $this->assertMatchesRegularExpression(
            '/protected function prepareForValidation\(\): void.*NodeMetaNormalizer::sanitize\(\$node\[\'meta\'\]\)/s',
            $content,
            'prepareForValidation must normalize node meta before the style rules run'
        );
    }

    public function test_import_and_patch_paths_normalize_meta(): void
    {
        foreach (['../src/Services/MapService.php', '../src/Models/Map.php', '../src/Services/NodeService.php'] as $path) {
            $content = file_get_contents(__DIR__ . '/' . $path);
            $this->assertStringContainsString('NodeMetaNormalizer::sanitize', $content, $path . ' must normalize meta on write');
        }
    }
}
