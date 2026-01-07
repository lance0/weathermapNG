<?php

namespace LibreNMS\Plugins\WeathermapNG\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authenticated users can create links
    }

    public function rules(): array
    {
        return [
            'src_node_id' => 'required|integer|exists:wmng_nodes,id',
            'dst_node_id' => 'required|integer|exists:wmng_nodes,id|different:src_node_id',
            'port_id_a' => 'nullable|integer|exists:ports,port_id',
            'port_id_b' => 'nullable|integer|exists:ports,port_id|different:port_id_a',
            'bandwidth_bps' => 'nullable|integer|min:1|max:10000000000000',
        ];
    }

    public function messages(): array
    {
        return [
            'src_node_id.required' => 'Source node is required',
            'src_node_id.exists' => 'Source node does not exist',
            'dst_node_id.required' => 'Destination node is required',
            'dst_node_id.exists' => 'Destination node does not exist',
            'dst_node_id.different' => 'Source and destination nodes must be different',
            'port_id_a.exists' => 'Source port does not exist',
            'port_id_b.exists' => 'Destination port does not exist',
            'port_id_b.different' => 'Source and destination ports must be different',
            'bandwidth_bps.min' => 'Bandwidth must be at least 1 bps',
            'bandwidth_bps.max' => 'Bandwidth cannot exceed 10 Gbps',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bandwidth_bps' => (int)($this->input('bandwidth_bps', 0)),
        ]);
    }
}
