<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
            'approved_at' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'total_amount' => ['required', 'integer', 'min:0'],
            'total_quantity' => ['required', 'integer', 'min:1'],
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            'source' => ['required', Rule::in(['CHECKOUT', 'ADMIN'])],
            'buyer_id' => ['required', 'integer', 'exists:buyers,id'],
            'code' => ['nullable', 'string', 'max:255', 'unique:orders,code'],
            'created_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'presentation_id' => ['required', 'integer', 'exists:presentations,id'],
            'payment_method' => ['required', Rule::in(['MERCADO_PAGO', 'CASH', 'BANK_TRANSFER', 'COMPLIMENTARY', 'OTHER'])],
            'status' => ['required', Rule::in(['PENDING', 'APPROVED', 'REJECTED', 'IN_PROCESS', 'WAIVED', 'CANCELED', 'EXPIRED', 'REFUNDED'])],
        ];
    }
}
