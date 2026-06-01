<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'provider' => ['nullable', 'string', 'max:80'],
            'provider_payment_id' => ['nullable', 'string', 'max:255'],
            'provider_preference_id' => ['nullable', 'string', 'max:255'],
            'provider_status' => ['nullable', 'string', 'max:80'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'raw_response' => ['nullable', 'array'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
