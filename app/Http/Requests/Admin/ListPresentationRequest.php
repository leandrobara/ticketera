<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ListPresentationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'show_id' => ['nullable', 'integer', 'exists:shows,id'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'sold_out', 'cancelled'])],
        ];
    }
}
