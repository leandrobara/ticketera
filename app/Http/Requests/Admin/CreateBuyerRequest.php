<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateBuyerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'dni' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'last_name' => ['nullable', 'string', 'max:160'],
        ];
    }
}
