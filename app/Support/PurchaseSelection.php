<?php

namespace App\Support;

use App\Models\Presentation;
use App\Models\SeasonTicketType;

class PurchaseSelection
{
    public static function validate(array $data, $validator): void
    {
        $presentation = Presentation::with('season.show')->find($data['presentation_id'] ?? null);
        $type = SeasonTicketType::find($data['season_ticket_type_id'] ?? null);
        if (! $presentation || ! $type || ! $presentation->season?->show || (int) $presentation->season_id !== (int) $type->season_id) {
            $validator->errors()->add('season_ticket_type_id', 'La entrada no pertenece a la temporada de esta función.');

            return;
        }
        if (! $type->is_active || $presentation->status !== 'published' || $presentation->season->status !== 'published' || ! $presentation->starts_at->isFuture()) {
            $validator->errors()->add('presentation_id', 'La función o entrada no está disponible para la venta.');
        }
        $code = $data['promo_code'] ?? null;
        if (filled($code) && (! $type->promotion_is_active || ! $type->promotion_type || $type->promotion_access_code !== $code)) {
            $validator->errors()->add('promo_code', 'El código no es válido para esta entrada.');
        }
    }
}
