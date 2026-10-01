<?php

namespace App\Http\Requests\OrderItemPricing;

use App\Support\PurchaseSelection;
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
            'presentation_id' => ['required', 'integer', 'exists:presentations,id'],
            'season_ticket_type_id' => [
                'required',
                'integer',
                'exists:season_ticket_types,id',
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
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            PurchaseSelection::validate($this->all(), $validator);
        });
    }
}
