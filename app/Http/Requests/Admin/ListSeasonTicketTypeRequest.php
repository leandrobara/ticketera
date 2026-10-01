<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListSeasonTicketTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'show_id' => ['nullable', 'integer', 'exists:shows,id'],
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'presentation_id' => ['nullable', 'integer', 'exists:presentations,id'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
