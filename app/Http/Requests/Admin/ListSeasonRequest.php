<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSeasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'show_id' => ['nullable', 'integer', 'exists:shows,id'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'finished', 'cancelled'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
