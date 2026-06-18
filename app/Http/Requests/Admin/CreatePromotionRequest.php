<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePromotionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('access_code')) {
            $accessCode = trim((string) $this->input('access_code'));
            $this->merge([
                'access_code' => $accessCode === '' ? null : mb_strtolower($accessCode),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'presentation_ticket_type_id' => [
                'required',
                'integer',
                'exists:presentation_ticket_types,id',
            ],
            'type' => ['required', Rule::in(['percent_discount', 'fixed_discount', 'buy_x_get_y'])],
            'value' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), ['percent_discount', 'fixed_discount'], true)),
                'nullable',
                'numeric',
                'decimal:0,6',
                'gt:0',
                Rule::when($this->input('type') === 'percent_discount', ['lte:100']),
            ],
            'bundle_quantity' => [
                Rule::requiredIf($this->input('type') === 'buy_x_get_y'),
                'nullable',
                'integer',
                'min:2',
            ],
            'pay_quantity' => [
                Rule::requiredIf($this->input('type') === 'buy_x_get_y'),
                'nullable',
                'integer',
                'min:1',
                'lt:bundle_quantity',
            ],
            'access_code' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z0-9-]+$/',
                'unique:promotions,access_code',
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (
                $this->filled('starts_at')
                && $this->filled('ends_at')
                && strtotime($this->input('ends_at')) <= strtotime($this->input('starts_at'))
            ) {
                $validator->errors()->add('ends_at', 'The ends at field must be after starts at.');
            }
        });
    }
}
