<?php

namespace App\Http\Requests\Checkout;

use App\Models\PresentationTicketType;
use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutOrderRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
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
            'presentation_ticket_type_id' => ['required', 'integer', 'exists:presentation_ticket_types,id'],
        ];
    }


    protected function prepareForValidation(): void
    {
        if ($this->has('promo_code')) {
            $promoCode = trim((string) $this->input('promo_code'));
            $this->merge([
                'promo_code' => $promoCode === '' ? null : mb_strtolower($promoCode),
            ]);
        }
    }


    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                if (blank(config('mercadopago.access_token'))) {
                    $validator->errors()->add('mercado_pago', 'mercado_pago_access_token_not_configured');
                    return;
                }

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

                if ($ticketType->presentation->season?->status !== 'published') {
                    $validator->errors()->add(
                        'presentation_ticket_type_id',
                        'season_not_published'
                    );
                }

                if ($ticketType->presentation->status !== 'published') {
                    $validator->errors()->add(
                        'presentation_ticket_type_id',
                        'presentation_not_published'
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
