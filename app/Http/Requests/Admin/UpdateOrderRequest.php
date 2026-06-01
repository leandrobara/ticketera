<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        $order = $this->route('order');

        return [
            'buyer_id' => ['nullable', 'integer', 'exists:buyers,id'],
            'presentation_id' => ['nullable', 'integer', 'exists:presentations,id'],
            'created_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('orders', 'code')->ignore($order)],
            'source' => ['nullable', Rule::in(['CHECKOUT', 'ADMIN'])],
            'status' => ['nullable', Rule::in(['PENDING', 'APPROVED', 'REJECTED', 'IN_PROCESS', 'WAIVED', 'CANCELED', 'EXPIRED', 'REFUNDED'])],
            'payment_method' => ['nullable', Rule::in(['MERCADO_PAGO', 'CASH', 'BANK_TRANSFER', 'COMPLIMENTARY', 'OTHER'])],
            'total_quantity' => ['nullable', 'integer', 'min:1'],
            'total_amount' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'approved_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
