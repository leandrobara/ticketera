<?php

namespace App\Support;

use App\Models\SeasonTicketType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketTypeData
{
    public static function validate(array $data, ?SeasonTicketType $existing = null): array
    {
        $data = array_merge($existing?->only(['season_id', 'name', 'price', 'stock', 'is_active', 'sort_order', 'promotion_name', 'promotion_type', 'promotion_value', 'promotion_bundle_quantity', 'promotion_pay_quantity', 'promotion_access_code', 'promotion_is_active']) ?? [], $data);
        $data['promotion_access_code'] = filled($data['promotion_access_code'] ?? null) ? mb_strtolower(trim($data['promotion_access_code'])) : null;
        if (empty($data['promotion_type'])) {
            foreach (['promotion_name', 'promotion_type', 'promotion_value', 'promotion_bundle_quantity', 'promotion_pay_quantity', 'promotion_access_code'] as $key) {
                $data[$key] = null;
            }
            $data['promotion_is_active'] = false;
        }
        $validated = Validator::make($data, [
            'season_id' => ['required', 'integer', 'exists:seasons,id'],
            'name' => 'required|string|max:160', 'price' => 'required|numeric|decimal:0,6|min:0',
            'stock' => 'nullable|integer|min:0', 'is_active' => 'sometimes|boolean', 'sort_order' => 'nullable|integer|min:1',
            'promotion_name' => 'nullable|string|max:160',
            'promotion_type' => 'nullable|in:percent_discount,fixed_discount,buy_x_get_y',
            'promotion_value' => 'nullable|required_if:promotion_type,percent_discount,fixed_discount|numeric|decimal:0,6|gt:0',
            'promotion_bundle_quantity' => 'nullable|required_if:promotion_type,buy_x_get_y|integer|min:2',
            'promotion_pay_quantity' => 'nullable|required_if:promotion_type,buy_x_get_y|integer|min:1',
            'promotion_access_code' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('season_ticket_types', 'promotion_access_code')->where('season_id', $data['season_id'] ?? 0)->whereNull('deleted_at')->ignore($existing?->id)],
            'promotion_is_active' => 'sometimes|boolean',
        ])->validate();
        if ($existing && (int) $existing->season_id !== (int) $validated['season_id']) {
            throw ValidationException::withMessages(['season_id' => 'No se puede trasladar una entrada a otra temporada.']);
        }
        $type = $validated['promotion_type'];
        if ($type === 'percent_discount' && (float) $validated['promotion_value'] > 100) {
            throw ValidationException::withMessages(['promotion_value' => 'El porcentaje no puede superar 100.']);
        }
        if ($type === 'fixed_discount' && (float) $validated['promotion_value'] > (float) $validated['price']) {
            throw ValidationException::withMessages(['promotion_value' => 'El descuento no puede superar el precio.']);
        }
        if ($type === 'buy_x_get_y' && $validated['promotion_pay_quantity'] >= $validated['promotion_bundle_quantity']) {
            throw ValidationException::withMessages(['promotion_pay_quantity' => 'La cantidad pagada debe ser menor que la recibida.']);
        }

        return $validated;
    }
}
