<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg', 'mimetypes:image/jpeg', 'extensions:jpg,jpeg', 'max:10240'],
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
