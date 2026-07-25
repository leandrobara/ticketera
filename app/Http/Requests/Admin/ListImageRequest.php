<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'show_id' => ['nullable', 'integer', 'exists:shows,id'],
            'type' => ['nullable', Rule::in(['gallery', 'grid'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
