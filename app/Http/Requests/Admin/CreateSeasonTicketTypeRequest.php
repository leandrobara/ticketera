<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateSeasonTicketTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return ['season_id' => 'required|integer|exists:seasons,id', 'name' => 'required', 'price' => 'required', 'stock' => 'nullable', 'is_active' => 'sometimes', 'sort_order' => 'sometimes', 'promotion_name' => 'nullable', 'promotion_type' => 'nullable', 'promotion_value' => 'nullable', 'promotion_bundle_quantity' => 'nullable', 'promotion_pay_quantity' => 'nullable', 'promotion_access_code' => 'nullable', 'promotion_is_active' => 'sometimes'];
    }
}
