<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListPresentationTicketTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'presentation_id' => ['nullable', 'integer', 'exists:presentations,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

