<?php

namespace App\Services\Api\Site;

use App\Models\Season;
use App\Models\Presentation;
use Illuminate\Support\Collection;
use App\Models\PresentationTicketType;
use App\Services\Api\OrderItemPricingService;
use App\Repositories\Site\PresentationRepository;


class PresentationService
{
    private const MAX_PURCHASE_QUANTITY = 10;

    public function __construct(
        private readonly PresentationRepository $presentationRepository,
        private readonly OrderItemPricingService $orderItemPricingService,
    ) {
        //
    }

    public function listBySeason(Season $season, array $data = []): Collection
    {
        $presentationsSalesData = collect();

        $presentations = $this->presentationRepository->listBySeason($season);
        $promotionAccessCode = $this->normalizeAccessCode($data['promo_code'] ?? null);

        foreach ($presentations as $presentation) {
            $isFinished = !$presentation->starts_at->isFuture();
            $availableTicketsCount = $isFinished ? 0 : $this->calculateAvailableTicketsCount($presentation);

            $ticketTypesForSaleData = [];

            if ($availableTicketsCount > 0) {
                foreach ($presentation->ticketTypes as $ticketType) {
                    // validamos si tenemos que mostrar las entradas en el front
                    if (!$this->shouldDisplayTicketType($ticketType, $promotionAccessCode)) {
                        continue;
                    }

                    // Si el tipo de entrada no tiene stock propio, usa la disponibilidad de la funcioón.
                    // Si tiene stock propio, descuenta las entradas ya vendidas sin permitir valores negativos.
                    $availableTicketsForTicketTypeCount = $ticketType->stock === null
                        ? $availableTicketsCount
                        : max(0, $ticketType->stock - $ticketType->sold_tickets_count)
                    ;

                    // Calculan cuántas entradas como máximo puede seleccionar una persona en esta compra.
                    $availableTicketsForPurchaseCount = min(
                        $availableTicketsCount,
                        $availableTicketsForTicketTypeCount,
                        self::MAX_PURCHASE_QUANTITY,
                    );
                    
                    // Contiene los tipos de entrada que se pueden ofrecer públicamente,
                    // junto con su disponibilidad máxima de compra y promoción aplicable.
                    $ticketTypesForSaleData[] = [
                        'ticket_type' => $ticketType,
                        'available_tickets_for_purchase_count' => $availableTicketsForPurchaseCount,
                        'promotion' => $this->buildPromotionData($ticketType, $promotionAccessCode),
                    ];
                }
            }

            // Guarda la función, si ya finalizó y los tipos de entrada disponibles para vender.
            $presentationsSalesData->push([
                'is_finished' => $isFinished,
                'presentation' => $presentation,
                'ticket_types' => $ticketTypesForSaleData,
            ]);
        }

        return $presentationsSalesData;
    }

    private function buildPromotionData(PresentationTicketType $ticketType, ?string $promotionAccessCode): ?array
    {
        $promotion = $this->orderItemPricingService->findApplicablePromotion($ticketType, $promotionAccessCode);
        if (!$promotion) {
            return null;
        }

        $calculatedPromoPricingForDisplay = $this->orderItemPricingService
            ->calculatePromotionPricingForDisplay($promotion)
        ;

        return [
            'name' => $promotion->promotion_name,
            'type' => $promotion->promotion_type,
            'value' => $promotion->promotion_value,
            'label' => $this->buildPromotionLabel($promotion),
            'pay_quantity' => $promotion->promotion_pay_quantity,
            'bundle_quantity' => $promotion->promotion_bundle_quantity,
            'promo_code_applied' => filled($promotion->promotion_access_code),
            'promotional_price' => $calculatedPromoPricingForDisplay['promotional_price'],
            'discount_amount' => $calculatedPromoPricingForDisplay['promotion_discount_amount'],
            'bundle_original_amount' => $calculatedPromoPricingForDisplay['promotion_bundle_original_amount'],
            'bundle_effective_amount' => $calculatedPromoPricingForDisplay['promotion_bundle_effective_amount'],
        ];
    }

    private function shouldDisplayTicketType(PresentationTicketType $ticketType, ?string $promotionAccessCode): bool
    {
        if (!$ticketType->promotion_is_active || blank($ticketType->promotion_access_code)) {
            return true;
        }

        return filled($promotionAccessCode) && $ticketType->promotion_access_code === $promotionAccessCode;
    }

    private function buildPromotionLabel(PresentationTicketType $ticketType): string
    {
        return match ($ticketType->promotion_type) {
            'percent_discount' => 'Promoción '.$this->formatPromotionValueForLabel($ticketType->promotion_value).'%',
            'fixed_discount' => 'Promoción -$'.$this->formatPromotionValueForLabel($ticketType->promotion_value),
            'buy_x_get_y' => 'Promoción '.$ticketType->promotion_bundle_quantity.'x'.$ticketType->promotion_pay_quantity,
            default => 'Promoción',
        };
    }

    private function formatPromotionValueForLabel(string|int|float|null $value): string
    {
        return rtrim(rtrim((string) $value, '0'), '.');
    }

    private function calculateAvailableTicketsCount(Presentation $presentation): int
    {
        if ($presentation->status !== 'published') {
            return 0;
        }

        return max(0, $presentation->capacity - $presentation->sold_tickets_count);
    }

    private function normalizeAccessCode(?string $promotionAccessCode): ?string
    {
        $promotionAccessCode = trim((string) $promotionAccessCode);
        return $promotionAccessCode === '' ? null : mb_strtolower($promotionAccessCode);
    }
}
