<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateShowRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:160'],
            'genre' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'string', 'max:100'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'main_image_path' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:180', 'alpha_dash'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'age_rating' => ['required', Rule::in(['ATP', '+13', '+16', '+18'])],
        ];
    }
}
