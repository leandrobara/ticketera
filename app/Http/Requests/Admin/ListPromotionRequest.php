<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPromotionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'type' => ['nullable', Rule::in(['percent_discount', 'fixed_discount', 'buy_x_get_y'])],
            'is_active' => ['nullable', 'boolean'],
            'access' => ['nullable', Rule::in(['public', 'code'])],
        ];
    }
}
