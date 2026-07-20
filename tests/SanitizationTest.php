<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Mirrors the production sanitization logic in the FormRequest classes
 * (CreateMapRequest, UpdateMapRequest, SaveMapRequest). FormRequest
 * requires a booted Laravel app, so each helper below is a faithful
 * standalone reimplementation of the corresponding sanitize() block,
 * not a call into the live class. Keep these in sync with the real
 * sanitize() methods — they assert the contract operators rely on.
 */
class SanitizationTest extends TestCase
{
    // --- CreateMapRequest-style sanitization ---

    private function sanitizeMapData(array $data): array
    {
        $data['name'] = strip_tags($data['name']);
        if (isset($data['title'])) {
            $data['title'] = strip_tags($data['title']);
        }
        return $data;
    }

    public function test_map_name_strips_html_tags(): void
    {
        $data = $this->sanitizeMapData(['name' => '<script>alert("xss")</script>my-map']);
        $this->assertEquals('alert("xss")my-map', $data['name']);
    }

    public function test_map_title_strips_html_tags(): void
    {
        $data = $this->sanitizeMapData(['name' => 'test', 'title' => '<b>Bold</b> Title']);
        $this->assertEquals('Bold Title', $data['title']);
    }

    public function test_map_sanitize_preserves_clean_input(): void
    {
        $data = $this->sanitizeMapData(['name' => 'my-network-map', 'title' => 'Network Map']);
        $this->assertEquals('my-network-map', $data['name']);
        $this->assertEquals('Network Map', $data['title']);
    }

    public function test_map_sanitize_without_title(): void
    {
        $data = $this->sanitizeMapData(['name' => 'test-map']);
        $this->assertEquals('test-map', $data['name']);
        $this->assertArrayNotHasKey('title', $data);
    }
    // --- SaveMapRequest node-label sanitization ---
    // Node-label normalization is now unit-tested directly against the
    // shared NodeLabelNormalizer in NodeLabelNormalizerTest (no mirrored
    // helper needed — that was drift risk PR #27 acknowledged). The
    // SanitizationTest keeps only the map/link helpers below, which mirror
    // sanitize() logic that still lives inline in the FormRequest classes.


    // --- Edge cases ---

    public function test_empty_string_sanitization(): void
    {
        $map = $this->sanitizeMapData(['name' => '']);
        $this->assertEquals('', $map['name']);
    }

    // Node-label empty-string and script-injection cases are covered by
    // NodeLabelNormalizerTest (real class, no mirror).

    public function test_nested_tags_fully_stripped(): void
    {
        $data = $this->sanitizeMapData(['name' => '<div><span><b>clean</b></span></div>']);
        $this->assertEquals('clean', $data['name']);
    }

    // test_script_injection_fully_stripped removed — covered by
    // NodeLabelNormalizerTest::test_normalize_strips_script_tags.


    // --- SaveMapRequest link style sanitization ---

    /**
     * Mirror of SaveMapRequest::sanitize() link style block — casts numeric
     * strings in via_points to floats so persisted JSON holds numbers, and
     * allowlists/strips link style color/width.
     */
    private function sanitizeLinkStyle(array $data): array
    {
        if (!empty($data['links']) && is_array($data['links'])) {
            foreach ($data['links'] as $i => $link) {
                if (!is_array($link) || !isset($link['style']) || !is_array($link['style'])) {
                    continue;
                }
                $style = $link['style'];

                $allowedLinkStyleKeys = ['via_style', 'via_points', 'color', 'width'];
                $style = array_intersect_key($style, array_flip($allowedLinkStyleKeys));
                if (isset($style['color']) && is_string($style['color'])) {
                    $style['color'] = strip_tags(trim($style['color']));
                }
                if (isset($style['width']) && is_numeric($style['width'])) {
                    $w = (float) $style['width'];
                    if ($w >= 0.5 && $w <= 20) {
                        $style['width'] = $w;
                    } else {
                        unset($style['width']);
                    }
                }
                $data['links'][$i]['style'] = $style;
                if (!empty($style['via_points']) && is_array($style['via_points'])) {
                    foreach ($style['via_points'] as $j => $vp) {
                        if (!is_array($vp)) {
                            continue;
                        }
                        if (isset($vp['x']) && is_numeric($vp['x'])) {
                            $data['links'][$i]['style']['via_points'][$j]['x'] = (float) $vp['x'];
                        }
                        if (isset($vp['y']) && is_numeric($vp['y'])) {
                            $data['links'][$i]['style']['via_points'][$j]['y'] = (float) $vp['y'];
                        }
                    }
                }
            }
        }
        return $data;
    }

    public function test_via_point_numeric_strings_cast_to_float(): void
    {
        $data = $this->sanitizeLinkStyle([
            'links' => [
                ['src_node_id' => 1, 'dst_node_id' => 2, 'style' => [
                    'via_style' => 'curved',
                    'via_points' => [['x' => '100', 'y' => '250']],
                ]],
            ],
        ]);
        $this->assertSame(100.0, $data['links'][0]['style']['via_points'][0]['x']);
        $this->assertSame(250.0, $data['links'][0]['style']['via_points'][0]['y']);
    }

    public function test_via_point_floats_preserved_as_float(): void
    {
        $data = $this->sanitizeLinkStyle([
            'links' => [
                ['style' => ['via_points' => [['x' => 50.5, 'y' => 75.25]]]],
            ],
        ]);
        $this->assertSame(50.5, $data['links'][0]['style']['via_points'][0]['x']);
        $this->assertSame(75.25, $data['links'][0]['style']['via_points'][0]['y']);
    }

    public function test_via_points_with_non_array_link_unchanged(): void
    {
        $data = $this->sanitizeLinkStyle([
            'links' => ['not-an-array'],
        ]);
        $this->assertSame(['not-an-array'], $data['links']);
    }

    public function test_via_points_missing_style_key_unchanged(): void
    {
        $data = $this->sanitizeLinkStyle([
            'links' => [['src_node_id' => 1, 'dst_node_id' => 2]],
        ]);
        $this->assertArrayNotHasKey('style', $data['links'][0]);
    }

    public function test_via_points_non_array_via_points_unchanged(): void
    {
        $data = $this->sanitizeLinkStyle([
            'links' => [['style' => ['via_points' => 'bogus']]],
        ]);
        $this->assertSame('bogus', $data['links'][0]['style']['via_points']);
    }

    public function test_via_points_empty_links_unchanged(): void
    {
        $data = $this->sanitizeLinkStyle(['nodes' => [['label' => 'r1']]]);
        $this->assertArrayNotHasKey('links', $data);
    }

    public function test_link_style_unknown_keys_are_stripped(): void
    {
        $data = $this->sanitizeLinkStyle([
            'links' => [['style' => ['via_style' => 'curved', 'bogus' => 'x', 'color' => '#ff0000', 'width' => 2.5]]],
        ]);
        $this->assertSame(['via_style' => 'curved', 'color' => '#ff0000', 'width' => 2.5], $data['links'][0]['style']);
    }

    public function test_link_style_color_html_is_stripped(): void
    {
        $data = $this->sanitizeLinkStyle([
            'links' => [['style' => ['color' => '<script>alert(1)</script>#ff0000', 'width' => 2]]],
        ]);
        $this->assertSame('alert(1)#ff0000', $data['links'][0]['style']['color']);
    }

    public function test_link_style_width_out_of_range_is_removed(): void
    {
        $data = $this->sanitizeLinkStyle([
            'links' => [['style' => ['width' => 99.0]]],
        ]);
        $this->assertArrayNotHasKey('width', $data['links'][0]['style']);
    }

    // --- SaveMapRequest tag sanitization ---

    private function sanitizeTags(array $data): array
    {
        if (!empty($data['options']['tags']) && is_array($data['options']['tags'])) {
            $tags = array_map(fn($t) => is_string($t) ? strtolower(strip_tags(trim($t))) : '', $data['options']['tags']);
            $tags = array_values(array_unique(array_filter($tags, fn($t) => $t !== '')));
            $data['options']['tags'] = $tags;
        }
        return $data;
    }

    public function test_tags_stripped_trimmed_lowercased_and_deduplicated(): void
    {
        $data = $this->sanitizeTags([
            'options' => ['tags' => [' core ', 'WAN', '', '<b>alert</b>', '  ', 'wan']],
        ]);
        $this->assertSame(['core', 'wan', 'alert'], $data['options']['tags']);
    }

    public function test_tags_rejects_non_strings_but_preserves_valid(): void
    {
        $data = $this->sanitizeTags([
            'options' => ['tags' => ['valid', 123, null, 'also-valid']],
        ]);
        $this->assertSame(['valid', 'also-valid'], $data['options']['tags']);
    }

    public function test_missing_tags_left_unchanged(): void
    {
        $data = $this->sanitizeTags(['options' => ['width' => 800]]);
        $this->assertArrayNotHasKey('tags', $data['options']);
    }

}
