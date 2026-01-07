<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authenticated users can create nodes
    }

    public function rules(): array
    {
        return [
            'label' => 'required|string|max:255|sanitize',
            'x' => 'required|integer|min:0|max:4096',
            'y' => 'required|integer|min:0|max:4096',
            'device_id' => 'nullable|integer|exists:devices,device_id',
            'meta' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Node label is required',
            'label.max' => 'Node label must not exceed 255 characters',
            'label.sanitize' => 'Node label contains invalid characters',
            'x.required' => 'X coordinate is required',
            'x.min' => 'X coordinate must be at least 0',
            'x.max' => 'X coordinate must not exceed 4096',
            'y.required' => 'Y coordinate is required',
            'y.min' => 'Y coordinate must be at least 0',
            'y.max' => 'Y coordinate must not exceed 4096',
            'device_id.exists' => 'Selected device does not exist',
        ];
    }

    public function sanitize(array $data): array
    {
        $data['label'] = strip_tags($data['label']);
        $data['label'] = htmlspecialchars($data['label'], ENT_QUOTES, 'UTF-8');
        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => trim($this->input('label', '')),
        ]);
    }
}
