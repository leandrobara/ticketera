<?php

namespace App\Services\Api\Site;

use App\Models\Presentation;
use App\Models\PresentationTicketType;
use App\Models\Season;
use App\Services\Api\OrderItemPricingService;
use Illuminate\Support\Collection;

class PresentationService
{
    private const MAX_PURCHASE_QUANTITY = 10;

    public function __construct(
        private readonly OrderItemPricingService $orderItemPricingService,
    ) {
        //
    }

    public function list(Season $season, array $data = []): Collection
    {
        $promoCode = $this->normalizePromoCode($data['promo_code'] ?? null);

        return Presentation::query()
            ->where('season_id', $season->id)
            ->whereIn('status', ['published', 'sold_out'])
            ->whereHas('season', function ($query) {
                $query->whereIn('status', ['published', 'finished']);
            })
            ->withCount([
                'tickets as sold_tickets_count' => function ($query) {
                    $query->whereIn('status', ['VALID', 'USED']);
                },
            ])
            ->with([
                'ticketTypes' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->withCount([
                            'tickets as sold_tickets_count' => function ($query) {
                                $query->whereIn('status', ['VALID', 'USED']);
                            },
                        ])
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Presentation $presentation) => $this->presentationPayload($presentation, $promoCode))
            ->values()
        ;
    }

    private function presentationPayload(Presentation $presentation, ?string $promoCode): array
    {
        $isFinished = !$presentation->starts_at->isFuture();
        $availableTickets = $this->presentationAvailableTickets($presentation, $isFinished);

        return [
            'id' => $presentation->id,
            'notes' => $presentation->notes,
            'starts_at' => $presentation->starts_at,
            'is_finished' => $isFinished,
            'tickets' => $presentation->ticketTypes
                ->filter(fn (PresentationTicketType $ticketType) => $this->ticketTypeIsVisible($ticketType, $promoCode))
                ->map(fn (PresentationTicketType $ticketType) => $this->ticketTypePayload(
                    $ticketType,
                    $availableTickets,
                    $promoCode,
                ))
                ->values()
                ->all(),
        ];
    }

    private function ticketTypePayload(
        PresentationTicketType $ticketType,
        int $presentationAvailableTickets,
        ?string $promoCode,
    ): array {
        $ticketTypeAvailableTickets = $ticketType->stock === null
            ? $presentationAvailableTickets
            : max(0, $ticketType->stock - $ticketType->sold_tickets_count);
        $availableTickets = min($presentationAvailableTickets, $ticketTypeAvailableTickets);
        $promotion = $this->resolvePromotion($ticketType, $promoCode);

        return [
            'id' => $ticketType->id,
            'name' => $ticketType->name,
            'price' => $ticketType->price,
            'has_stock' => $availableTickets > 0,
            'max_purchase_quantity' => min(self::MAX_PURCHASE_QUANTITY, $availableTickets),
            'promotion' => $promotion,
        ];
    }

    private function resolvePromotion(PresentationTicketType $ticketType, ?string $promoCode): ?array
    {
        $promotion = $this->orderItemPricingService->resolvePromotion($ticketType, true, $promoCode);

        if (!$promotion) {
            return null;
        }

        $promotionDisplay = $this->orderItemPricingService->calculatePromotionDisplay($promotion);

        return [
            'name' => $promotion->promotion_name,
            'type' => $promotion->promotion_type,
            'value' => $promotion->promotion_value,
            'label' => $this->promotionLabel($promotion),
            'promotional_price' => $promotionDisplay['promotional_price'],
            'discount_amount' => $promotionDisplay['promotion_discount_amount'],
            'pay_quantity' => $promotion->promotion_pay_quantity,
            'bundle_quantity' => $promotion->promotion_bundle_quantity,
            'bundle_original_amount' => $promotionDisplay['promotion_bundle_original_amount'],
            'bundle_effective_amount' => $promotionDisplay['promotion_bundle_effective_amount'],
            'promo_code_applied' => filled($promotion->promotion_access_code),
        ];
    }

    private function ticketTypeIsVisible(PresentationTicketType $ticketType, ?string $promoCode): bool
    {
        if (!$ticketType->promotion_is_active || blank($ticketType->promotion_access_code)) {
            return true;
        }

        return filled($promoCode) && $ticketType->promotion_access_code === $promoCode;
    }

    private function presentationAvailableTickets(Presentation $presentation, bool $isFinished): int
    {
        if ($presentation->status !== 'published' || $isFinished) {
            return 0;
        }

        return max(0, $presentation->capacity - $presentation->sold_tickets_count);
    }

    private function promotionLabel(PresentationTicketType $ticketType): string
    {
        return match ($ticketType->promotion_type) {
            'percent_discount' => 'Promoción '.$this->formatPromotionValue($ticketType->promotion_value).'%',
            'fixed_discount' => 'Promoción -$'.$this->formatPromotionValue($ticketType->promotion_value),
            'buy_x_get_y' => 'Promoción '.$ticketType->promotion_bundle_quantity.'x'.$ticketType->promotion_pay_quantity,
            default => 'Promoción',
        };
    }

    private function formatPromotionValue(string|int|float|null $value): string
    {
        return rtrim(rtrim((string) $value, '0'), '.');
    }

    private function normalizePromoCode(?string $promoCode): ?string
    {
        $promoCode = trim((string) $promoCode);

        return $promoCode === '' ? null : mb_strtolower($promoCode);
    }
}
