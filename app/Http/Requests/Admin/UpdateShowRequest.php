<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShowRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'synopsis' => ['nullable', 'string'],
            'additional_information' => ['nullable', 'string'],
            'production_note' => ['nullable', 'string'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'x_url' => ['nullable', 'url', 'max:255'],
            'tiktok_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'pinterest_url' => ['nullable', 'url', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'faqs' => ['nullable', 'array'],
            'faqs.*' => ['array'],
            'faqs.*.question' => ['required_with:faqs', 'string', 'max:255'],
            'faqs.*.answer' => ['required_with:faqs', 'string'],
            'faqs.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'whatsapp_phone' => ['nullable', 'regex:/^\+?[1-9][0-9]{6,14}$/'],
            'comments_enabled' => ['sometimes', 'boolean'],
            'title' => ['nullable', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'in:theater'],
            'service_fee_type' => [
                'required',
                Rule::in(['fixed_amount', 'percentage']),
            ],
            'service_fee_fixed_amount' => [
                'nullable',
                'numeric',
                'decimal:0,6',
                'min:0',
                'required_if:service_fee_type,fixed_amount',
                'prohibited_if:service_fee_type,percentage',
            ],
            'service_fee_percentage' => [
                'nullable',
                'numeric',
                'decimal:0,6',
                'min:0',
                'max:100',
                'required_if:service_fee_type,percentage',
                'prohibited_if:service_fee_type,fixed_amount',
            ],
            'service_fee_minimum_unit_amount' => [
                'nullable',
                'numeric',
                'decimal:0,6',
                'min:0',
            ],
            'age_rating' => ['nullable', Rule::in(['ATP', '+13', '+16', '+18'])],
            'slug' => ['nullable', 'string', 'max:180', 'alpha_dash'],
        ];
    }
}
