<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin gate is enforced in MapController::save() via requireAdmin().
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'options' => 'nullable|array',
            'options.width' => 'nullable|integer|min:100|max:4096',
            'options.height' => 'nullable|integer|min:100|max:4096',
            'options.background' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/',
            'options.tags' => 'nullable|array|max:50',
            'options.tags.*' => 'nullable|string|max:50|regex:/^[a-z0-9_-]+$/i',
            'options.default_node_style' => 'nullable|array:color,label_color',
            'options.default_node_style.color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/',
            'options.default_node_style.label_color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/',
            'options.default_link_style' => 'nullable|array:color,width,via_style',
            'options.default_link_style.color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/',
            'options.default_link_style.width' => 'nullable|numeric|min:0.5|max:20',
            'options.default_link_style.via_style' => 'nullable|string|in:straight,angled,curved',

            // Keys must be present (empty arrays are intentional clears).
            'nodes' => 'present|array|max:2000',
            'nodes.*.id' => 'nullable',
            'nodes.*.label' => 'nullable|string|max:255',
            'nodes.*.x' => 'nullable|numeric|min:0|max:10000',
            'nodes.*.y' => 'nullable|numeric|min:0|max:10000',
            'nodes.*.device_id' => 'nullable|integer',
            'nodes.*.meta' => 'nullable|array',

            'links' => 'present|array|max:2000',
            'links.*.src_node_id' => 'nullable',
            'links.*.dst_node_id' => 'nullable',
            'links.*.source' => 'nullable',
            'links.*.target' => 'nullable',
            'links.*.src' => 'nullable',
            'links.*.dst' => 'nullable',
            'links.*.port_id_a' => 'nullable|integer',
            'links.*.port_id_b' => 'nullable|integer',
            'links.*.port_a' => 'nullable|integer',
            'links.*.port_b' => 'nullable|integer',
            'links.*.bandwidth_bps' => 'nullable|numeric|min:0',
            'links.*.bandwidth' => 'nullable|numeric|min:0',
            'links.*.style' => 'nullable|array:via_style,via_points,color,width',
            'links.*.style.via_style' => 'nullable|string|in:straight,angled,curved',
            'links.*.style.color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/',
            'links.*.style.width' => 'nullable|numeric|min:0.5|max:20',
            'links.*.style.via_points' => 'nullable|array|max:200',
            'links.*.style.via_points.*' => 'array:x,y',
            'links.*.style.via_points.*.x' => 'required|numeric|min:0|max:10000',
            'links.*.style.via_points.*.y' => 'required|numeric|min:0|max:10000',
        ];
    }

    public function messages(): array
    {
        return [
            'nodes.present' => 'Save payload must include a "nodes" array',
            'links.present' => 'Save payload must include a "links" array',
            'nodes.max' => 'A map cannot have more than 2000 nodes',
            'links.max' => 'A map cannot have more than 2000 links',
            'options.width.min' => 'Width must be at least 100 pixels',
            'options.width.max' => 'Width must not exceed 4096 pixels',
            'options.height.min' => 'Height must be at least 100 pixels',
            'options.height.max' => 'Height must not exceed 4096 pixels',
            'options.background.regex' => 'Background must be a valid hex color (e.g., #ffffff)',
            'options.tags.max' => 'A map cannot have more than 50 tags',
            'options.tags.*.max' => 'Each tag must not exceed 50 characters',
            'options.default_node_style.color.regex' => 'Default node color must be a valid hex color (e.g., #28a745)',
            'options.default_node_style.label_color.regex' => 'Default node label color must be a valid hex color',
            'options.default_link_style.color.regex' => 'Default link color must be a valid hex color',
            'options.default_link_style.width.min' => 'Default link width must be at least 0.5',
            'options.default_link_style.width.max' => 'Default link width must not exceed 20',
            'options.default_link_style.via_style.in' => 'Default link style must be straight, angled, or curved',
            'links.*.style.color.regex' => 'Link style color must be a valid hex color',
            'links.*.style.width.min' => 'Link width must be at least 0.5',
            'links.*.style.width.max' => 'Link width must not exceed 20',
            'options.tags.*.regex' => 'Tags may only contain letters, numbers, hyphens and underscores',
            'links.*.style.array' => 'Link style may only contain via_style, via_points, color, or width',
            'links.*.style.via_style.in' => 'Via style must be straight, angled, or curved',
            'links.*.style.via_points.*.x.numeric' => 'Via point x must be numeric',
            'links.*.style.via_points.*.y.numeric' => 'Via point y must be numeric',
            'links.*.style.via_points.*.x.min' => 'Via point x must be at least 0',
            'links.*.style.via_points.*.x.max' => 'Via point x must not exceed 10000',
            'links.*.style.via_points.*.y.min' => 'Via point y must be at least 0',
            'links.*.style.via_points.*.y.max' => 'Via point y must not exceed 10000',
            'links.*.style.via_points.max' => 'A link cannot have more than 200 via points',
        ];
    }

    /**
     * Ensure each link entry has at least one source and destination id key.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $links = $this->input('links', []);
            if (!is_array($links)) {
                return;
            }

            foreach ($links as $index => $link) {
                if (!is_array($link)) {
                    $validator->errors()->add("links.$index", 'Each link must be an object');
                    continue;
                }

                $src = $link['src_node_id'] ?? $link['source'] ?? $link['src'] ?? null;
                $dst = $link['dst_node_id'] ?? $link['target'] ?? $link['dst'] ?? null;

                if ($src === null || $src === '') {
                    $validator->errors()->add(
                        "links.$index.src_node_id",
                        'Each link requires a source node id (src_node_id, source, or src)'
                    );
                }
                if ($dst === null || $dst === '') {
                    $validator->errors()->add(
                        "links.$index.dst_node_id",
                        'Each link requires a destination node id (dst_node_id, target, or dst)'
                    );
                }
            }
        });
    }

    public function sanitize(array $data): array
    {
        foreach (['title', 'name'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = strip_tags(trim($data[$key]));
            }
        }

        if (!empty($data['options']['tags']) && is_array($data['options']['tags'])) {
            $tags = array_map(fn($t) => is_string($t) ? strtolower(strip_tags(trim($t))) : '', $data['options']['tags']);
            $tags = array_values(array_unique(array_filter($tags, fn($t) => $t !== '')));
            $data['options']['tags'] = $tags;
        }

        $allowedNodeStyleKeys = ['color', 'label_color'];
        if (!empty($data['options']['default_node_style']) && is_array($data['options']['default_node_style'])) {
            $style = array_intersect_key($data['options']['default_node_style'], array_flip($allowedNodeStyleKeys));
            $style = array_map(fn($v) => is_string($v) ? strip_tags(trim($v)) : $v, $style);
            $data['options']['default_node_style'] = array_filter($style, fn($v) => $v !== null && $v !== '');
        }

        $allowedLinkStyleKeys = ['color', 'width', 'via_style'];
        if (!empty($data['options']['default_link_style']) && is_array($data['options']['default_link_style'])) {
            $style = array_intersect_key($data['options']['default_link_style'], array_flip($allowedLinkStyleKeys));
            $style = array_map(fn($v) => is_string($v) ? strip_tags(trim($v)) : $v, $style);
            if (isset($style['width']) && is_numeric($style['width'])) {
                $width = (float) $style['width'];
                if ($width >= 0.5 && $width <= 20) {
                    $style['width'] = $width;
                } else {
                    unset($style['width']);
                }
            }
            $data['options']['default_link_style'] = array_filter($style, fn($v) => $v !== null && $v !== '');
        }

        if (!empty($data['nodes']) && is_array($data['nodes'])) {
            foreach ($data['nodes'] as $i => $node) {
                if (!is_array($node)) {
                    continue;
                }
                if (isset($node['label']) && is_string($node['label'])) {
                    $data['nodes'][$i]['label'] = strip_tags(trim($node['label']));
                }
            }
        }

        if (!empty($data['links']) && is_array($data['links'])) {
            foreach ($data['links'] as $i => $link) {
                if (!is_array($link) || !isset($link['style']) || !is_array($link['style'])) {
                    continue;
                }
                $style = $link['style'];

                // Validation allowlists keys; here we sanitize and cast numeric strings
                // so the persisted JSON holds numbers, not stringified coords.
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

    protected function prepareForValidation(): void
    {
        foreach (['title', 'name'] as $key) {
            if ($this->has($key) && is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key))]);
            }
        }
    }
}
