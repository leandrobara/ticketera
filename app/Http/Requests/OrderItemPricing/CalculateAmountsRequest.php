<?php

namespace App\Http\Requests\OrderItemPricing;

use App\Models\PresentationTicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateAmountsRequest extends FormRequest
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
            'presentation_ticket_type_id' => [
                'required',
                'integer',
                'exists:presentation_ticket_types,id',
            ],
            'promo_code' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z0-9-]+$/',
                'prohibited_if:payment_method,FREE',
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'payment_method' => ['required', Rule::in(['FREE', 'BANK_TRANSFER', 'CASH', 'MERCADO_PAGO'])],
        ];
    }

    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
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
