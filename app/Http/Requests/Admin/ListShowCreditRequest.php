<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListShowCreditRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'show_id' => ['nullable', 'integer', 'exists:shows,id'],
            'person_id' => ['nullable', 'integer', 'exists:people,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'section' => ['nullable', Rule::in(['cast', 'technical'])],
            'role_label' => ['nullable', 'string', 'max:160'],
        ];
    }
}
