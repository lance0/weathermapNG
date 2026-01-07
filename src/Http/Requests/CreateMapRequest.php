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
        ];
    }

    public function sanitize(array $data): array
    {
        $data['name'] = strip_tags($data['name']);
        if (isset($data['title'])) {
            $data['title'] = strip_tags($data['title']);
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
