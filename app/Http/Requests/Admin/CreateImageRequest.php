<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            'path' => ['prohibited'],
            'type' => ['required', Rule::in(['gallery', 'grid'])],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_main' => ['nullable', 'boolean'],
        ];
    }
}
