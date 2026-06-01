<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;


class UpdateShowRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string'],
            'title' => ['nullable', 'string', 'max:160'],
            'genre' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'string', 'max:100'],
            'main_image_path' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'age_rating' => ['nullable', Rule::in(['ATP', '+13', '+16', '+18'])],
            'slug' => ['nullable', 'string', 'max:180', 'alpha_dash'],
        ];
    }

}
