<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'provider' => ['nullable', 'string', 'max:80'],
            'provider_status' => ['nullable', 'string', 'max:80'],
        ];
    }
}
