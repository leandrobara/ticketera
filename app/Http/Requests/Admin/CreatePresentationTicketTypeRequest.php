<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreatePresentationTicketTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:160'],
            'price' => ['required', 'integer', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            'presentation_id' => ['required', 'integer', 'exists:presentations,id'],
        ];
    }
}

