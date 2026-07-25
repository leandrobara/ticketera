<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListShowLinkRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'show_id' => ['nullable', 'integer', 'exists:shows,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
