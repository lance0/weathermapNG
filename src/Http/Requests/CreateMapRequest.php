<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authenticated users can create maps
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|alpha_dash|regex:/^[a-z0-9_-]+$/i',
            'title' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9\s\-_.,!]+$/',
            'width' => 'nullable|integer|min:100|max:4096',
            'height' => 'nullable|integer|min:100|max:4096',
            'options' => 'nullable|array:tags,default_node_style,default_link_style,background',
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
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Map name is required',
            'name.regex' => 'Map name can only contain letters, numbers, hyphens and underscores',
            'name.max' => 'Map name must not exceed 255 characters',
            'title.max' => 'Title must not exceed 255 characters',
            'width.min' => 'Width must be at least 100 pixels',
            'width.max' => 'Width must not exceed 4096 pixels',
            'height.min' => 'Height must be at least 100 pixels',
            'height.max' => 'Height must not exceed 4096 pixels',
            'options.tags.max' => 'A map cannot have more than 50 tags',
            'options.background.regex' => 'Background must be a valid hex color',
            'options.default_node_style.color.regex' => 'Default node color must be a valid hex color',
            'options.default_link_style.width.min' => 'Default link width must be at least 0.5',
        ];
    }

    public function sanitize(array $data): array
    {
        $data['name'] = strip_tags($data['name']);
        if (isset($data['title'])) {
            $data['title'] = strip_tags($data['title']);
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
                $style['width'] = ($width >= 0.5 && $width <= 20) ? $width : null;
            }
            $data['options']['default_link_style'] = array_filter($style, fn($v) => $v !== null && $v !== '');
        }
        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim($this->input('name', '')),
            'title' => trim($this->input('title', '')),
        ]);
    }
}
