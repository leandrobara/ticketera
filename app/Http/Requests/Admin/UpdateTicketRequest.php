<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function rules(): array
    {
        $ticket = $this->route('ticket');

        return [
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'presentation_id' => ['nullable', 'integer', 'exists:presentations,id'],
            'presentation_ticket_type_id' => ['nullable', 'integer', 'exists:presentation_ticket_types,id'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('tickets', 'code')->ignore($ticket)],
            'status' => ['nullable', Rule::in(['VALID', 'USED', 'CANCELED'])],
            'checked_in_at' => ['nullable', 'date'],
            'canceled_at' => ['nullable', 'date'],
        ];
    }
}
