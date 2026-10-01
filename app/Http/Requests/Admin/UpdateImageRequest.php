<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:3072'],
            'show_id' => ['nullable', 'integer', 'exists:shows,id'],
            'path' => ['prohibited'],
            'type' => ['nullable', Rule::in(['gallery', 'grid'])],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_main' => ['nullable', 'boolean'],
        ];
    }
}
