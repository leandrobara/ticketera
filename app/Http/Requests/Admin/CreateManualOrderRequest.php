<?php

namespace App\Http\Requests\Admin;

use App\Models\PresentationTicketType;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateManualOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('promo_code')) {
            $promoCode = trim((string) $this->input('promo_code'));
            $this->merge([
                'promo_code' => $promoCode === '' ? null : mb_strtolower($promoCode),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::in(['FREE', 'BANK_TRANSFER', 'CASH'])],
            'promo_code' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z0-9-]+$/',
                'prohibited_if:payment_method,FREE',
            ],
            'buyer' => ['required', 'array'],
            'buyer.dni' => ['nullable', 'string', 'max:20'],
            'buyer.name' => ['required', 'string', 'max:160'],
            'buyer.email' => ['required', 'email', 'max:255'],
            'buyer.phone' => ['nullable', 'string', 'max:40'],
            'buyer.last_name' => ['nullable', 'string', 'max:160'],
            'presentation_ticket_type_id' => ['required', 'integer', 'exists:presentation_ticket_types,id'],
        ];
    }

    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $ticketType = PresentationTicketType::query()
                    ->with('presentation.season')
                    ->find($this->input('presentation_ticket_type_id'));

                if (!$ticketType?->presentation) {
                    $validator->errors()->add(
                        'presentation_ticket_type_id',
                        'presentation_ticket_type_without_presentation'
                    );
                    return;
                }

                if (!$ticketType->presentation->season?->show_id) {
                    $validator->errors()->add(
                        'presentation_ticket_type_id',
                        'presentation_ticket_type_without_season'
                    );
                }

                if (!$ticketType->is_active) {
                    $validator->errors()->add(
                        'presentation_ticket_type_id',
                        'presentation_ticket_type_not_active'
                    );
                }

                $promoCode = $this->input('promo_code');

                if (blank($promoCode)) {
                    return;
                }

                $promotionTicketType = PresentationTicketType::query()
                    ->where('promotion_access_code', $promoCode)
                    ->where('promotion_is_active', true)
                    ->whereNotNull('promotion_type')
                    ->first();

                if (!$promotionTicketType) {
                    $validator->errors()->add('promo_code', 'invalid_promo_code');
                    return;
                }

                if ((int) $promotionTicketType->id !== (int) $this->input('presentation_ticket_type_id')) {
                    $validator->errors()->add('promo_code', 'promo_code_not_available_for_ticket_type');
                }
            });
        }
    }
}
