<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'decimal:0,6', 'min:0'],
            'subtotal_amount' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'presentation_ticket_type_id' => ['nullable', 'integer', 'exists:presentation_ticket_types,id'],
        ];
    }
}
