<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'bio' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'photo_path' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:160'],
            'document_type' => ['nullable', 'string', 'max:30', 'required_with:document_number'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:80', 'required_with:document_type'],
            'allow_duplicate_name' => ['nullable', 'boolean'],
        ];
    }
}
