<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMapVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'auto_save' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Version name is required',
            'name.max' => 'Version name must not exceed 100 characters',
            'description.max' => 'Description must not exceed 1000 characters',
            'auto_save' => 'Auto-save must be a boolean value',
        ];
    }
}
