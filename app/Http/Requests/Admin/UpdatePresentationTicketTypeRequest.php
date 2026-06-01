<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePresentationTicketTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'presentation_id' => ['nullable', 'integer', 'exists:presentations,id'],
            'name' => ['nullable', 'string', 'max:160'],
            'price' => ['nullable', 'integer', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

