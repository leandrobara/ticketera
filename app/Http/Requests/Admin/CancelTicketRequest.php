<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CancelTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ticket = $this->route('ticket');

            if ($ticket?->status !== 'VALID') {
                $validator->errors()->add(
                    'ticket',
                    'only_valid_tickets_can_be_canceled'
                );
            }
        });
    }
}
