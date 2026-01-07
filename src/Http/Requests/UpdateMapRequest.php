<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add policy check in future for ownership
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9\s\-_.,!]+$/',
            'width' => 'nullable|integer|min:100|max:4096',
            'height' => 'nullable|integer|min:100|max:4096',
            'background' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/',
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'Title must not exceed 255 characters',
            'width.min' => 'Width must be at least 100 pixels',
            'width.max' => 'Width must not exceed 4096 pixels',
            'height.min' => 'Height must be at least 100 pixels',
            'height.max' => 'Height must not exceed 4096 pixels',
            'background.regex' => 'Background must be a valid hex color (e.g., #ffffff)',
        ];
    }

    public function sanitize(array $data): array
    {
        if (isset($data['title'])) {
            $data['title'] = strip_tags($data['title']);
        }
        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim($this->input('title', '')),
            'background' => trim($this->input('background', '')),
        ]);
    }
}
