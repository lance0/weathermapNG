<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

use LibreNMS\Plugins\WeathermapNG\Models\Map;
use LibreNMS\Plugins\WeathermapNG\Models\Node;
use LibreNMS\Plugins\WeathermapNG\Models\Link;

/**
 * Serializer and parser for the legacy PHP Weathermap-style `.conf` format.
 *
 * Shape (see config/maps/example.conf):
 *
 *   [global]
 *   width 800
 *   height 600
 *   title "Example Network Map"
 *   background_color #ffffff
 *
 *   [node:router1]
 *   label "Core Router 1"
 *   x 200
 *   y 150
 *   device_id 1
 *
 *   [link:router1-router2]
 *   nodes router1 router2
 *   bandwidth 1000000000
 *   label "1Gbps Backbone"
 *
 * The parser accepts the subset of directives this plugin round-trips, plus
 * `interface_id` and `metric` on nodes (recorded into node `meta`), because
 * legacy configs commonly carry them. Unknown directives inside a section are
 * ignored rather than rejected, so hand-edited files still load.
 */
class LegacyConfService
{
    public const FORMAT_ERROR = 'Invalid legacy config file: %s';

    public function toConf(Map $map): string
    {
        $nodes = $map->nodes->sortBy('id')->values();
        $labelToId = [];
        foreach ($nodes as $i => $node) {
            // Stable, shell-safe node identifiers derived from position.
            $labelToId[$node->id] = 'n' . ($i + 1);
        }

        $lines = ['[global]'];
        $lines[] = 'width ' . ($map->width ?? 800);
        $lines[] = 'height ' . ($map->height ?? 600);
        if (!empty($map->title)) {
            $lines[] = 'title ' . $this->quote($map->title);
        }
        $bg = is_array($map->options) ? ($map->options['background'] ?? '#ffffff') : '#ffffff';
        $lines[] = 'background_color ' . $this->colorOr($bg, '#ffffff');
        $lines[] = '';

        foreach ($nodes as $node) {
            $lines[] = '[node:' . $labelToId[$node->id] . ']';
            $lines[] = 'label ' . $this->quote($node->label ?? '');
            $lines[] = 'x ' . (int) $node->x;
            $lines[] = 'y ' . (int) $node->y;
            if (!empty($node->device_id)) {
                $lines[] = 'device_id ' . (int) $node->device_id;
            }
            if (!empty($node->meta['interface_id'])) {
                $lines[] = 'interface_id ' . (int) $node->meta['interface_id'];
            }
            $lines[] = '';
        }

        foreach ($map->links->sortBy('id') as $link) {
            $src = $labelToId[$link->src_node_id] ?? null;
            $dst = $labelToId[$link->dst_node_id] ?? null;
            if ($src === null || $dst === null) {
                continue;
            }
            $lines[] = '[link:' . $src . '-' . $dst . ']';
            $lines[] = 'nodes ' . $src . ' ' . $dst;
            if (!empty($link->bandwidth_bps)) {
                $lines[] = 'bandwidth ' . (int) $link->bandwidth_bps;
            }
            $styleLabel = is_array($link->style) ? ($link->style['label'] ?? null) : null;
            if (!empty($styleLabel)) {
                $lines[] = 'label ' . $this->quote((string) $styleLabel);
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Parse a `.conf` file into the same array shape the JSON import uses,
     * so MapService::importMap() can handle both formats uniformly.
     *
     * @return array{options: array, nodes: list<array>, links: list<array>}
     */
    public function parse(string $content, string $name): array
    {
        $global = [];
        $nodes = [];
        $links = [];

        $section = null;
        $sectionData = [];

        $flush = function () use (&$section, &$sectionData, &$global, &$nodes, &$links) {
            if ($section === 'global') {
                $global = $sectionData;
            } elseif (is_string($section) && str_starts_with($section, 'node:')) {
                $sectionData['__key'] = trim(substr($section, 5));
                $nodes[] = $sectionData;
            } elseif (is_string($section) && str_starts_with($section, 'link:')) {
                $sectionData['__key'] = trim(substr($section, 5));
                $links[] = $sectionData;
            }
            $section = null;
            $sectionData = [];
        };

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, ';')) {
                continue;
            }

            if (preg_match('/^\[(.+)\]$/', $trimmed, $m)) {
                $flush();
                $section = trim($m[1]);
                $sectionData = [];
                continue;
            }

            if ($section === null) {
                throw new \InvalidArgumentException("Directive outside any section: `{$trimmed}`");
            }

            $parts = preg_split('/\s+/', $trimmed, 2);
            $key = strtolower($parts[0]);
            $value = $parts[1] ?? '';

            if ($key === 'label' || $key === 'title') {
                $sectionData[$key === 'title' ? 'title' : 'label'] = $this->unquote($value);
            } else {
                $sectionData[$key] = $value;
            }
        }
        $flush();

        $width = isset($global['width']) && is_numeric($global['width']) ? (int) $global['width'] : 800;
        $height = isset($global['height']) && is_numeric($global['height']) ? (int) $global['height'] : 600;
        $background = $this->colorOr($global['background_color'] ?? $global['background'] ?? '#ffffff', '#ffffff');
        $title = isset($global['title']) ? trim($global['title']) : $name;

        // `width`/`height` clamped to what CreateMapCommand allows so a bogus
        // conf can't break the editor.
        $width = max(100, min(4096, $width));
        $height = max(100, min(4096, $height));

        $nodeList = [];
        $keyToId = [];
        foreach ($nodes as $i => $n) {
            if (empty($n['__key'])) {
                continue;
            }
            $key = $n['__key'];
            if (isset($keyToId[$key])) {
                throw new \InvalidArgumentException("Duplicate node section: `[node:{$key}]`");
            }
            $nodeList[] = [
                'label' => $n['label'] ?? $key,
                'x' => is_numeric($n['x'] ?? null) ? (float) $n['x'] : ($i + 1) * 100.0,
                'y' => is_numeric($n['y'] ?? null) ? (float) $n['y'] : 100.0,
                'device_id' => is_numeric($n['device_id'] ?? null) ? (int) $n['device_id'] : null,
                'meta' => array_filter([
                    'interface_id' => is_numeric($n['interface_id'] ?? null) ? (int) $n['interface_id'] : null,
                    'metric' => $n['metric'] ?? null,
                ]),
            ];
            $keyToId[$key] = count($nodeList) - 1;
        }

        $linkList = [];
        foreach ($links as $l) {
            $endpoints = preg_split('/\s+/', $l['nodes'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
            if (count($endpoints) !== 2 || !isset($keyToId[$endpoints[0]]) || !isset($keyToId[$endpoints[1]])) {
                throw new \InvalidArgumentException(
                    sprintf("Link section `[link:%s]` has no usable `nodes` line (both ends must reference declared nodes)", $l['__key'] ?? '?')
                );
            }
            $linkList[] = [
                'src' => $keyToId[$endpoints[0]],
                'dst' => $keyToId[$endpoints[1]],
                'bandwidth_bps' => is_numeric($l['bandwidth'] ?? null) ? (int) $l['bandwidth'] : null,
                'style' => isset($l['label']) ? ['label' => $l['label']] : [],
            ];
        }

        return [
            'options' => array_filter([
                'width' => $width,
                'height' => $height,
                'background' => $background,
            ]),
            'nodes' => $nodeList,
            'links' => $linkList,
        ];
    }

    private function quote(string $value): string
    {
        return '"' . str_replace('"', "", $value) . '"';
    }

    private function unquote(string $value): string
    {
        $value = trim($value);
        if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private function colorOr($value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : $fallback;
    }
}
