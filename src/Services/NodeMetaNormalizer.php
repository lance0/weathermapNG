<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

/**
 * Normalizes the per-node style keys stored flat in `wmng_nodes.meta`.
 *
 * Style values can arrive from saves, imports, node PATCH calls and restored
 * version snapshots. Invalid values must never be stored and must never block
 * an unrelated save, so every write path routes meta through here:
 *
 *   - `color` / `label_color`  #RRGGBB, lowercased
 *   - `shape`                  circle | square | diamond | triangle
 *   - `size`                   numeric 6..40
 *
 * Every other meta key (interface_id, sub_map_id, tooltip, ...) is preserved
 * verbatim. Invalid style keys are dropped; they are never replaced with a
 * default, so the node falls back to its device/status rendering.
 */
final class NodeMetaNormalizer
{
    private const STYLE_KEYS = ['color', 'label_color', 'shape', 'size'];
    private const SHAPES = ['circle', 'square', 'diamond', 'triangle'];
    private const MIN_SIZE = 6;
    private const MAX_SIZE = 40;

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    public static function sanitize(array $meta): array
    {
        $out = $meta;
        foreach (self::STYLE_KEYS as $key) {
            unset($out[$key]);
        }

        $style = [];

        foreach (['color', 'label_color'] as $key) {
            if (isset($meta[$key]) && is_string($meta[$key])) {
                $value = strtolower(strip_tags(trim($meta[$key])));
                if (preg_match('/^#[0-9a-f]{6}$/', $value)) {
                    $style[$key] = $value;
                }
            }
        }

        if (isset($meta['shape']) && is_string($meta['shape'])) {
            $shape = strtolower(strip_tags(trim($meta['shape'])));
            if (in_array($shape, self::SHAPES, true)) {
                $style['shape'] = $shape;
            }
        }

        if (isset($meta['size']) && is_numeric($meta['size'])) {
            $size = (float) $meta['size'];
            if ($size >= self::MIN_SIZE && $size <= self::MAX_SIZE) {
                $style['size'] = $size;
            }
        }

        return $out + $style;
    }
}
