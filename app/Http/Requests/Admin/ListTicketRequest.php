<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'presentation_id' => ['nullable', 'integer', 'exists:presentations,id'],
            'presentation_ticket_type_id' => ['nullable', 'integer', 'exists:presentation_ticket_types,id'],
            'status' => ['nullable', Rule::in(['VALID', 'USED', 'CANCELED'])],
        ];
    }
}
