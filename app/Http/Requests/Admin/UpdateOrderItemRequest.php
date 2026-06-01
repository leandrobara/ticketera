<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'presentation_ticket_type_id' => ['nullable', 'integer', 'exists:presentation_ticket_types,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'unit_price' => ['nullable', 'integer', 'min:0'],
            'total_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
