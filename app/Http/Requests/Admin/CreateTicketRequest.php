<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'canceled_at' => ['nullable', 'date'],
            'checked_in_at' => ['nullable', 'date'],
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'status' => ['nullable', Rule::in(['VALID', 'USED', 'CANCELED'])],
            'code' => ['nullable', 'string', 'max:255', 'unique:tickets,code'],
            'presentation_id' => ['required', 'integer', 'exists:presentations,id'],
            'presentation_ticket_type_id' => ['nullable', 'integer', 'exists:presentation_ticket_types,id'],
        ];
    }
}
