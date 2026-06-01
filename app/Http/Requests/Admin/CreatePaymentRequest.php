<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'paid_at' => ['nullable', 'date'],
            'raw_response' => ['nullable', 'array'],
            'amount' => ['required', 'integer', 'min:0'],
            'provider' => ['nullable', 'string', 'max:80'],
            'currency' => ['nullable', 'string', 'size:3'],
            'provider_status' => ['nullable', 'string', 'max:80'],
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'provider_payment_id' => ['nullable', 'string', 'max:255'],
            'provider_preference_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
