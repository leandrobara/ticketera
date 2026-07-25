<?php

namespace App\Http\Requests\Admin;

use App\Models\Presentation;
use App\Models\PresentationTicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePresentationTicketTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:160'],
            'price' => ['required', 'numeric', 'decimal:0,6', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'presentation_id' => ['required', 'integer', 'exists:presentations,id'],
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
                Rule::unique('presentation_ticket_types', 'promotion_access_code')->whereNull('deleted_at'),
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
                $presentation = Presentation::find($this->input('presentation_id'));

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
        if (!$this->boolean('promotion_is_active') && blank($this->input('promotion_type'))) {
            return;
        }

        $type = $this->input('promotion_type');

        if (blank($type)) {
            $validator->errors()->add('promotion_type', 'promotion_type_required');
            return;
        }

        if (in_array($type, ['percent_discount', 'fixed_discount'], true) && blank($this->input('promotion_value'))) {
            $validator->errors()->add('promotion_value', 'promotion_value_required');
        }

        if ($type === 'percent_discount' && (float) $this->input('promotion_value') > 100) {
            $validator->errors()->add('promotion_value', 'percent_discount_must_be_less_than_or_equal_to_100');
        }

        if ($type !== 'buy_x_get_y') {
            return;
        }

        if (blank($this->input('promotion_bundle_quantity'))) {
            $validator->errors()->add('promotion_bundle_quantity', 'promotion_bundle_quantity_required');
        }

        if (blank($this->input('promotion_pay_quantity'))) {
            $validator->errors()->add('promotion_pay_quantity', 'promotion_pay_quantity_required');
        }

        if (
            filled($this->input('promotion_bundle_quantity'))
            && filled($this->input('promotion_pay_quantity'))
            && (int) $this->input('promotion_pay_quantity') >= (int) $this->input('promotion_bundle_quantity')
        ) {
            $validator->errors()->add('promotion_pay_quantity', 'promotion_pay_quantity_must_be_less_than_bundle_quantity');
        }
    }

    private function validateDuplicatePromotion($validator): void
    {
        if (!$this->promotionWillBeActive() || blank($this->input('promotion_type'))) {
            return;
        }

        $query = $this->getDuplicatePromotionQuery();

        if (!$query || !$query->exists()) {
            return;
        }

        $validator->errors()->add('promotion_type', 'duplicate_promotion_for_presentation');
    }

    private function getDuplicatePromotionQuery()
    {
        $type = $this->input('promotion_type');
        $query = PresentationTicketType::query()
            ->where('presentation_id', $this->input('presentation_id'))
            ->where('promotion_is_active', true)
            ->where('promotion_type', $type);

        if (filled($this->input('promotion_access_code'))) {
            $query->where('promotion_access_code', $this->input('promotion_access_code'));
        } else {
            $query->whereNull('promotion_access_code');
        }

        if (in_array($type, ['percent_discount', 'fixed_discount'], true)) {
            if (blank($this->input('promotion_value'))) {
                return null;
            }

            return $query
                ->where('promotion_value', $this->input('promotion_value'))
                ->whereNull('promotion_bundle_quantity')
                ->whereNull('promotion_pay_quantity');
        }

        if ($type === 'buy_x_get_y') {
            if (blank($this->input('promotion_bundle_quantity')) || blank($this->input('promotion_pay_quantity'))) {
                return null;
            }

            return $query
                ->whereNull('promotion_value')
                ->where('promotion_bundle_quantity', $this->input('promotion_bundle_quantity'))
                ->where('promotion_pay_quantity', $this->input('promotion_pay_quantity'));
        }

        return null;
    }

    private function promotionWillBeActive(): bool
    {
        if ($this->has('promotion_is_active')) {
            return $this->boolean('promotion_is_active');
        }

        return filled($this->input('promotion_type'));
    }
}
