<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'buyer_id' => ['nullable', 'integer', 'exists:buyers,id'],
            'presentation_id' => ['nullable', 'integer', 'exists:presentations,id'],
            'source' => ['nullable', Rule::in(['CHECKOUT', 'ADMIN'])],
            'status' => ['nullable', Rule::in(['PENDING', 'APPROVED', 'REJECTED', 'IN_PROCESS', 'WAIVED', 'CANCELED', 'EXPIRED', 'REFUNDED'])],
            'payment_method' => ['nullable', Rule::in(['MERCADO_PAGO', 'CASH', 'BANK_TRANSFER', 'COMPLIMENTARY', 'OTHER'])],
        ];
    }
}
