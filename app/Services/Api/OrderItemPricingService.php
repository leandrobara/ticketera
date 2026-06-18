<?php

namespace App\Services\Api;

use App\Models\Promotion;
use App\Models\PresentationTicketType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class OrderItemPricingService
{
    private const SCALE = 6;

    public function resolvePromotion(
        PresentationTicketType $ticketType,
        bool $applyPromotion = true,
        ?string $promoCode = null,
    ): ?Promotion {
        if (!$applyPromotion || blank($promoCode)) {
            return null;
        }

        $promotion = $ticketType->promotion()
            ->where('is_active', true)
            ->where('access_code', $promoCode)
            ->first();

        if (!$promotion) {
            return null;
        }

        if ($promotion->starts_at && $promotion->starts_at->isFuture()) {
            return null;
        }

        if ($promotion->ends_at && $promotion->ends_at->isPast()) {
            return null;
        }

        return $promotion;
    }

    public function calculateAmounts(array $data): array
    {
        $ticketType = PresentationTicketType::findOrFail(
            $data['presentation_ticket_type_id']
        );

        $promotion = $this->resolvePromotion(
            $ticketType,
            $data['payment_method'] !== 'FREE',
            $data['promo_code'] ?? null
        );

        $unitPriceOverride = $data['payment_method'] === 'FREE'
            ? '0.000000'
            : null;

        return [
            ...$this->calculateAmountsForTicketType(
                $ticketType,
                $data['quantity'],
                $promotion,
                $unitPriceOverride
            ),
            'promotion' => $promotion,
        ];
    }

    public function calculateAmountsForTicketType(
        PresentationTicketType $ticketType,
        int $quantity,
        ?Promotion $promotion = null,
        ?string $unitPriceOverride = null,
    ): array {
        $unitPrice = $this->decimal($unitPriceOverride ?? $ticketType->price);
        $subtotal = $unitPrice->multipliedBy($quantity);
        $discount = BigDecimal::zero();

        if ($promotion) {
            $discount = match ($promotion->type) {
                'percent_discount' => $this->calculatePercentDiscount($subtotal, $promotion),
                'fixed_discount' => $this->calculateFixedDiscount($unitPrice, $quantity, $promotion),
                'buy_x_get_y' => $this->calculateBuyXGetYDiscount($unitPrice, $subtotal, $quantity, $promotion),
                default => throw ValidationException::withMessages([
                    'promotion' => ['unsupported_promotion_type'],
                ]),
            };
        }

        if ($discount->isGreaterThan($subtotal)) {
            $discount = $subtotal;
        }

        return [
            'unit_price' => $this->format($unitPrice),
            'subtotal_amount' => $this->format($subtotal),
            'discount_amount' => $this->format($discount),
            'total_amount' => $this->format($subtotal->minus($discount)),
        ];
    }

    private function calculatePercentDiscount(BigDecimal $subtotal, Promotion $promotion): BigDecimal
    {
        return $subtotal
            ->multipliedBy($promotion->value)
            ->dividedBy('100', self::SCALE, RoundingMode::HalfUp);
    }

    private function calculateFixedDiscount(
        BigDecimal $unitPrice,
        int $quantity,
        Promotion $promotion,
    ): BigDecimal {
        $discountPerTicket = $this->decimal($promotion->value);

        if ($discountPerTicket->isGreaterThan($unitPrice)) {
            throw ValidationException::withMessages([
                'promotion' => ['fixed_discount_exceeds_unit_price'],
            ]);
        }

        return $discountPerTicket->multipliedBy($quantity);
    }

    private function calculateBuyXGetYDiscount(
        BigDecimal $unitPrice,
        BigDecimal $subtotal,
        int $quantity,
        Promotion $promotion,
    ): BigDecimal {
        $bundleQuantity = $promotion->bundle_quantity;
        $payQuantity = $promotion->pay_quantity;

        if (!$bundleQuantity || !$payQuantity || $payQuantity >= $bundleQuantity) {
            throw ValidationException::withMessages([
                'promotion' => ['invalid_buy_x_get_y_configuration'],
            ]);
        }

        $completeBundles = intdiv($quantity, $bundleQuantity);
        $remainingUnits = $quantity % $bundleQuantity;
        $paidUnits = ($completeBundles * $payQuantity) + $remainingUnits;
        $total = $unitPrice->multipliedBy($paidUnits);

        return $subtotal->minus($total);
    }

    private function decimal(string $value): BigDecimal
    {
        return BigDecimal::of($value)->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    private function format(BigDecimal $value): string
    {
        return (string) $value->toScale(self::SCALE, RoundingMode::HalfUp);
    }
}
