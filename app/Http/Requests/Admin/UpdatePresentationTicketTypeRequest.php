<?php

namespace App\Http\Requests\Admin;

use App\Models\Presentation;
use App\Models\PresentationTicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePresentationTicketTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'presentation_id' => ['nullable', 'integer', 'exists:presentations,id'],
            'name' => ['nullable', 'string', 'max:160'],
            'price' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'promotion_name' => ['nullable', 'string', 'max:160'],
            'promotion_type' => ['nullable', Rule::in(['percent_discount', 'fixed_discount', 'buy_x_get_y'])],
            'promotion_value' => ['nullable', 'numeric', 'decimal:0,6', 'gt:0'],
            'promotion_bundle_quantity' => ['nullable', 'integer', 'min:2'],
            'promotion_pay_quantity' => ['nullable', 'integer', 'min:1'],
            'promotion_access_code' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('presentation_ticket_types', 'promotion_access_code')
                    ->ignore($this->route('presentationTicketType'))
                    ->whereNull('deleted_at'),
            ],
            'promotion_is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('promotion_access_code')) {
            $accessCode = trim((string) $this->input('promotion_access_code'));
            $this->merge([
                'promotion_access_code' => $accessCode === '' ? null : mb_strtolower($accessCode),
            ]);
        }
    }

    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $ticketType = $this->route('presentationTicketType');
                $presentationId = $this->input('presentation_id', $ticketType?->presentation_id);
                $presentation = Presentation::query()->with('season')->find($presentationId);

                if (!$presentation) {
                    return;
                }

                if (!$presentation->season?->show_id) {
                    $validator->errors()->add(
                        'presentation_id',
                        'The selected presentation does not belong to a season with a show.'
                    );
                }

                $this->validatePromotionFields($validator);
                $this->validateDuplicatePromotion($validator);
            });
        }
    }

    private function validatePromotionFields($validator): void
    {
        $ticketType = $this->route('presentationTicketType');
        $promotionIsActive = $this->has('promotion_is_active')
            ? $this->boolean('promotion_is_active')
            : (bool) $ticketType?->promotion_is_active;
        $type = $this->input('promotion_type', $ticketType?->promotion_type);

        if (!$promotionIsActive && blank($type)) {
            return;
        }

        if (blank($type)) {
            $validator->errors()->add('promotion_type', 'promotion_type_required');
            return;
        }

        $value = $this->input('promotion_value', $ticketType?->promotion_value);

        if (in_array($type, ['percent_discount', 'fixed_discount'], true) && blank($value)) {
            $validator->errors()->add('promotion_value', 'promotion_value_required');
        }

        if ($type === 'percent_discount' && (float) $value > 100) {
            $validator->errors()->add('promotion_value', 'percent_discount_must_be_less_than_or_equal_to_100');
        }

        if ($type !== 'buy_x_get_y') {
            return;
        }

        $bundleQuantity = $this->input('promotion_bundle_quantity', $ticketType?->promotion_bundle_quantity);
        $payQuantity = $this->input('promotion_pay_quantity', $ticketType?->promotion_pay_quantity);

        if (blank($bundleQuantity)) {
            $validator->errors()->add('promotion_bundle_quantity', 'promotion_bundle_quantity_required');
        }

        if (blank($payQuantity)) {
            $validator->errors()->add('promotion_pay_quantity', 'promotion_pay_quantity_required');
        }

        if (filled($bundleQuantity) && filled($payQuantity) && (int) $payQuantity >= (int) $bundleQuantity) {
            $validator->errors()->add('promotion_pay_quantity', 'promotion_pay_quantity_must_be_less_than_bundle_quantity');
        }
    }

    private function validateDuplicatePromotion($validator): void
    {
        $ticketType = $this->route('presentationTicketType');
        $promotionIsActive = $this->promotionWillBeActive();
        $type = $this->input('promotion_type', $ticketType?->promotion_type);

        if (!$promotionIsActive || blank($type)) {
            return;
        }

        $query = $this->getDuplicatePromotionQuery($type);

        if (!$query || !$query->exists()) {
            return;
        }

        $validator->errors()->add('promotion_type', 'duplicate_promotion_for_presentation');
    }

    private function getDuplicatePromotionQuery(?string $type)
    {
        $ticketType = $this->route('presentationTicketType');
        $promotionAccessCode = $this->input('promotion_access_code', $ticketType?->promotion_access_code);
        $query = PresentationTicketType::query()
            ->where('presentation_id', $this->input('presentation_id', $ticketType?->presentation_id))
            ->where('id', '!=', $ticketType?->id)
            ->where('promotion_is_active', true)
            ->where('promotion_type', $type);

        if (filled($promotionAccessCode)) {
            $query->where('promotion_access_code', $promotionAccessCode);
        } else {
            $query->whereNull('promotion_access_code');
        }

        if (in_array($type, ['percent_discount', 'fixed_discount'], true)) {
            $value = $this->input('promotion_value', $ticketType?->promotion_value);

            if (blank($value)) {
                return null;
            }

            return $query
                ->where('promotion_value', $value)
                ->whereNull('promotion_bundle_quantity')
                ->whereNull('promotion_pay_quantity');
        }

        if ($type === 'buy_x_get_y') {
            $bundleQuantity = $this->input('promotion_bundle_quantity', $ticketType?->promotion_bundle_quantity);
            $payQuantity = $this->input('promotion_pay_quantity', $ticketType?->promotion_pay_quantity);

            if (blank($bundleQuantity) || blank($payQuantity)) {
                return null;
            }

            return $query
                ->whereNull('promotion_value')
                ->where('promotion_bundle_quantity', $bundleQuantity)
                ->where('promotion_pay_quantity', $payQuantity);
        }

        return null;
    }

    private function promotionWillBeActive(): bool
    {
        $ticketType = $this->route('presentationTicketType');

        if ($this->has('promotion_is_active')) {
            return $this->boolean('promotion_is_active');
        }

        if ($this->has('promotion_type') && filled($this->input('promotion_type'))) {
            return true;
        }

        return (bool) $ticketType?->promotion_is_active;
    }
}
