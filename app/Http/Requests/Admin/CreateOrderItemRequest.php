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
            'paid_quantity' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['required', 'numeric', 'decimal:0,6', 'min:0'],
            'unit_service_fee' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'service_fee_type' => ['nullable', 'in:fixed_amount,percentage'],
            'service_fee_fixed_amount' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'service_fee_percentage' => ['nullable', 'numeric', 'decimal:0,6', 'min:0', 'max:100'],
            'service_fee_base_amount' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'service_fee_minimum_applied' => ['nullable', 'boolean'],
            'service_fee_minimum_unit_amount' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'subtotal_amount' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'service_fee_total_amount' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'presentation_ticket_type_id' => ['nullable', 'integer', 'exists:presentation_ticket_types,id'],
        ];
    }
}
