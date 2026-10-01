<?php

namespace App\Http\Requests\Checkout;

use App\Support\PurchaseSelection;
use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'mercado_pago_device_id' => ['nullable', 'string', 'max:255'],
            'attribution' => ['nullable', 'array'],
            'attribution.utm_source' => ['nullable', 'string', 'max:255'],
            'attribution.utm_medium' => ['nullable', 'string', 'max:255'],
            'attribution.utm_campaign' => ['nullable', 'string', 'max:255'],
            'attribution.utm_content' => ['nullable', 'string', 'max:255'],
            'attribution.utm_term' => ['nullable', 'string', 'max:255'],
            'attribution.fbclid' => ['nullable', 'string', 'max:1024'],
            'attribution.fbc' => ['nullable', 'string', 'max:255'],
            'attribution.fbp' => ['nullable', 'string', 'max:255'],
            'promo_code' => [
                'max:80',
                'string',
                'nullable',
                'regex:/^[a-z0-9-]+$/',
            ],
            'buyer' => ['required', 'array'],
            'buyer.dni' => ['nullable', 'string', 'max:20'],
            'buyer.name' => ['required', 'string', 'max:160'],
            'buyer.email' => ['required', 'email', 'max:255'],
            'buyer.phone' => ['nullable', 'string', 'max:40'],
            'buyer.last_name' => ['nullable', 'string', 'max:160'],
            'presentation_id' => ['required', 'integer', 'exists:presentations,id'],
            'season_ticket_type_id' => ['required', 'integer', 'exists:season_ticket_types,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mercado_pago_device_id')) {
            $mercadoPagoDeviceId = trim((string) $this->input('mercado_pago_device_id'));

            $this->merge([
                'mercado_pago_device_id' => $mercadoPagoDeviceId === '' ? null : $mercadoPagoDeviceId,
            ]);
        }

        if ($this->has('promo_code')) {
            $promoCode = trim((string) $this->input('promo_code'));
            $this->merge([
                'promo_code' => $promoCode === '' ? null : mb_strtolower($promoCode),
            ]);
        }

        if (! is_array($this->input('attribution'))) {
            return;
        }

        $attribution = $this->input('attribution');
        $attributionFields = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_content',
            'utm_term',
            'fbclid',
            'fbc',
            'fbp',
        ];

        foreach ($attributionFields as $attributionField) {
            if (! array_key_exists($attributionField, $attribution)) {
                continue;
            }

            $value = trim((string) $attribution[$attributionField]);
            $attribution[$attributionField] = $value === '' ? null : $value;
        }

        $this->merge(['attribution' => $attribution]);
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
