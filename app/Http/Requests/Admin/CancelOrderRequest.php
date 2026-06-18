<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $order = $this->route('order');

            if ($order?->tickets()->where('status', 'USED')->exists()) {
                $validator->errors()->add(
                    'order',
                    'orders_with_used_tickets_cannot_be_canceled'
                );
                return;
            }

            if (!$order?->tickets()->where('status', 'VALID')->exists()) {
                $validator->errors()->add(
                    'order',
                    'order_has_no_valid_tickets_to_cancel'
                );
            }
        });
    }
}
