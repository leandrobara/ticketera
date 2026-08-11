<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'role' => ['nullable', 'string', Rule::in(User::roles())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
