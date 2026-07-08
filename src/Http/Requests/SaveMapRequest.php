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
            'options' => 'nullable|array',
            'options.width' => 'nullable|integer|min:100|max:4096',
            'options.height' => 'nullable|integer|min:100|max:4096',
            'options.background' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/',

            // Keys must be present (empty arrays are intentional clears).
            'nodes' => 'required|array|max:2000',
            'nodes.*.id' => 'nullable',
            'nodes.*.label' => 'nullable|string|max:255',
            'nodes.*.x' => 'nullable|numeric|min:0|max:10000',
            'nodes.*.y' => 'nullable|numeric|min:0|max:10000',
            'nodes.*.device_id' => 'nullable|integer',
            'nodes.*.meta' => 'nullable|array',

            'links' => 'required|array|max:2000',
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
            'links.*.style' => 'nullable|array:via_style,via_points',
            'links.*.style.via_style' => 'nullable|string|in:straight,angled,curved',
            'links.*.style.via_points' => 'nullable|array|max:200',
            'links.*.style.via_points.*' => 'array:x,y',
            'links.*.style.via_points.*.x' => 'required|numeric|min:0|max:10000',
            'links.*.style.via_points.*.y' => 'required|numeric|min:0|max:10000',
        ];
    }

    public function messages(): array
    {
        return [
            'nodes.required' => 'Save payload must include a "nodes" array',
            'links.required' => 'Save payload must include a "links" array',
            'nodes.max' => 'A map cannot have more than 2000 nodes',
            'links.max' => 'A map cannot have more than 2000 links',
            'options.width.min' => 'Width must be at least 100 pixels',
            'options.width.max' => 'Width must not exceed 4096 pixels',
            'options.height.min' => 'Height must be at least 100 pixels',
            'options.height.max' => 'Height must not exceed 4096 pixels',
            'options.background.regex' => 'Background must be a valid hex color (e.g., #ffffff)',
            'links.*.style.array' => 'Link style may only contain via_style and via_points',
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
        if (isset($data['title']) && is_string($data['title'])) {
            $data['title'] = strip_tags($data['title']);
        }

        if (!empty($data['nodes']) && is_array($data['nodes'])) {
            foreach ($data['nodes'] as $i => $node) {
                if (!is_array($node)) {
                    continue;
                }
                if (isset($node['label']) && is_string($node['label'])) {
                    $data['nodes'][$i]['label'] = strip_tags($node['label']);
                }
            }
        }

        if (!empty($data['links']) && is_array($data['links'])) {
            foreach ($data['links'] as $i => $link) {
                if (!is_array($link) || !isset($link['style']) || !is_array($link['style'])) {
                    continue;
                }
                $style = $link['style'];

                // Validation allowlists keys; here we cast numeric strings
                // so the persisted JSON holds numbers, not stringified coords.
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
        if ($this->has('title') && is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }
}
