<?php

namespace App\Services\Api;

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
    ): ?PresentationTicketType {
        if (!$applyPromotion) {
            return null;
        }

        if (!$ticketType->promotion_is_active || blank($ticketType->promotion_type)) {
            return null;
        }

        if (filled($promoCode) && $ticketType->promotion_access_code !== $promoCode) {
            return null;
        }

        if (blank($promoCode) && filled($ticketType->promotion_access_code)) {
            return null;
        }

        return $ticketType;
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
                $unitPriceOverride,
                $this->chargesServiceFee($data['payment_method'])
            ),
            'promotion' => $promotion,
        ];
    }

    public function calculateAmountsForTicketType(
        PresentationTicketType $ticketType,
        int $quantity,
        ?PresentationTicketType $promotion = null,
        ?string $unitPriceOverride = null,
        bool $chargeServiceFee = true,
    ): array {
        $ticketType->loadMissing('presentation.season.show');
        $show = $ticketType->presentation?->season?->show;

        $unitPrice = $this->decimal($unitPriceOverride ?? $ticketType->price);
        $subtotal = $unitPrice->multipliedBy($quantity);
        $discount = BigDecimal::zero();
        $paidQuantity = $quantity;

        if ($promotion) {
            $discount = match ($promotion->promotion_type) {
                'percent_discount' => $this->calculatePercentDiscount($subtotal, $promotion),
                'fixed_discount' => $this->calculateFixedDiscount($unitPrice, $quantity, $promotion),
                'buy_x_get_y' => $this->calculateBuyXGetYDiscount($unitPrice, $subtotal, $quantity, $promotion),
                default => throw ValidationException::withMessages([
                    'promotion' => ['unsupported_promotion_type'],
                ]),
            };

            if ($promotion->promotion_type === 'buy_x_get_y') {
                $paidQuantity = $this->calculateBuyXGetYPaidQuantity($quantity, $promotion);
            }
        }

        if ($discount->isGreaterThan($subtotal)) {
            $discount = $subtotal;
        }

        $ticketAmount = $subtotal->minus($discount);
        $serviceFeeType = $show?->service_fee_type ?? 'fixed_amount';
        $serviceFeeFixedAmount = $show?->service_fee_fixed_amount;
        $serviceFeePercentage = $show?->service_fee_percentage;
        $serviceFeeMinimumUnitAmount = $show?->service_fee_minimum_unit_amount;
        $serviceFee = $chargeServiceFee
            ? $this->calculateServiceFee(
                $ticketAmount,
                $paidQuantity,
                $serviceFeeType,
                $serviceFeeFixedAmount,
                $serviceFeePercentage,
            )
            : BigDecimal::zero();
        $minimumFeeResult = $this->applyMinimumServiceFee(
            $serviceFee,
            $ticketAmount,
            $paidQuantity,
            $serviceFeeMinimumUnitAmount,
            $chargeServiceFee,
        );
        $serviceFee = $minimumFeeResult['service_fee'];
        $unitServiceFee = $paidQuantity > 0
            ? $serviceFee->dividedBy($paidQuantity, self::SCALE, RoundingMode::HalfUp)
            : BigDecimal::zero();

        return [
            'paid_quantity' => $paidQuantity,
            'unit_price' => $this->format($unitPrice),
            'unit_service_fee' => $this->format($unitServiceFee),
            'service_fee_type' => $serviceFeeType,
            'service_fee_fixed_amount' => $serviceFeeFixedAmount,
            'service_fee_percentage' => $serviceFeePercentage,
            'service_fee_base_amount' => $this->format($ticketAmount),
            'service_fee_minimum_applied' => $minimumFeeResult['minimum_applied'],
            'service_fee_minimum_unit_amount' => $serviceFeeMinimumUnitAmount === null
                ? null
                : $this->format($this->decimal((string) $serviceFeeMinimumUnitAmount)),
            'subtotal_amount' => $this->format($subtotal),
            'discount_amount' => $this->format($discount),
            'service_fee_total_amount' => $this->format($serviceFee),
            'total_amount' => $this->format($ticketAmount->plus($serviceFee)),
        ];
    }

    public function getPromotionName(PresentationTicketType $ticketType): string
    {
        if (filled($ticketType->promotion_name)) {
            return $ticketType->promotion_name;
        }

        return match ($ticketType->promotion_type) {
            'percent_discount' => (float) $ticketType->promotion_value.'% OFF',
            'fixed_discount' => '$'.(float) $ticketType->promotion_value.' OFF',
            'buy_x_get_y' => $ticketType->promotion_bundle_quantity.'x'.$ticketType->promotion_pay_quantity,
            default => $ticketType->name,
        };
    }

    public function calculatePromotionDisplay(PresentationTicketType $ticketType): array
    {
        $isBundlePromotion = $ticketType->promotion_type === 'buy_x_get_y';
        $displayQuantity = $isBundlePromotion
            ? (int) $ticketType->promotion_bundle_quantity
            : 1;
        $pricing = $this->calculateAmountsForTicketType(
            $ticketType,
            $displayQuantity,
            $ticketType,
            null,
            false,
        );

        return [
            'promotional_price' => $isBundlePromotion ? null : $pricing['total_amount'],
            'promotion_discount_amount' => $pricing['discount_amount'],
            'promotion_label' => match ($ticketType->promotion_type) {
                'percent_discount' => (float) $ticketType->promotion_value.'% OFF',
                'fixed_discount' => '$'.(float) $ticketType->promotion_value.' OFF',
                'buy_x_get_y' => $ticketType->promotion_bundle_quantity.'x'.$ticketType->promotion_pay_quantity,
                default => null,
            },
            'promotion_bundle_original_amount' => $isBundlePromotion
                ? $pricing['subtotal_amount']
                : null,
            'promotion_bundle_effective_amount' => $isBundlePromotion
                ? $pricing['total_amount']
                : null,
        ];
    }

    private function calculatePercentDiscount(BigDecimal $subtotal, PresentationTicketType $promotion): BigDecimal
    {
        return $subtotal
            ->multipliedBy($promotion->promotion_value)
            ->dividedBy('100', self::SCALE, RoundingMode::HalfUp);
    }

    private function calculateFixedDiscount(
        BigDecimal $unitPrice,
        int $quantity,
        PresentationTicketType $promotion,
    ): BigDecimal {
        $discountPerTicket = $this->decimal($promotion->promotion_value);

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
        PresentationTicketType $promotion,
    ): BigDecimal {
        $bundleQuantity = $promotion->promotion_bundle_quantity;
        $payQuantity = $promotion->promotion_pay_quantity;

        if (!$bundleQuantity || !$payQuantity || $payQuantity >= $bundleQuantity) {
            throw ValidationException::withMessages([
                'promotion' => ['invalid_buy_x_get_y_configuration'],
            ]);
        }

        $completeBundles = intdiv($quantity, $bundleQuantity);
        $remainingUnits = $quantity % $bundleQuantity;
        $paidQuantity = ($completeBundles * $payQuantity) + $remainingUnits;
        $total = $unitPrice->multipliedBy($paidQuantity);

        return $subtotal->minus($total);
    }

    private function calculateBuyXGetYPaidQuantity(int $quantity, PresentationTicketType $promotion): int
    {
        $bundleQuantity = $promotion->promotion_bundle_quantity;
        $payQuantity = $promotion->promotion_pay_quantity;

        if (!$bundleQuantity || !$payQuantity || $payQuantity >= $bundleQuantity) {
            throw ValidationException::withMessages([
                'promotion' => ['invalid_buy_x_get_y_configuration'],
            ]);
        }

        $completeBundles = intdiv($quantity, $bundleQuantity);
        $remainingUnits = $quantity % $bundleQuantity;

        return ($completeBundles * $payQuantity) + $remainingUnits;
    }

    private function calculateServiceFee(
        BigDecimal $baseAmount,
        int $paidQuantity,
        string $serviceFeeType,
        string|int|float|null $fixedAmount,
        string|int|float|null $percentage,
    ): BigDecimal {
        return match ($serviceFeeType) {
            'fixed_amount' => $this->decimal((string) ($fixedAmount ?? 0))
                ->multipliedBy($paidQuantity),
            'percentage' => $baseAmount
                ->multipliedBy((string) ($percentage ?? 0))
                ->dividedBy('100', self::SCALE, RoundingMode::HalfUp),
            default => throw ValidationException::withMessages([
                'service_fee_type' => ['unsupported_service_fee_type'],
            ]),
        };
    }

    private function applyMinimumServiceFee(
        BigDecimal $serviceFee,
        BigDecimal $baseAmount,
        int $paidQuantity,
        string|int|float|null $minimumUnitAmount,
        bool $chargeServiceFee,
    ): array {
        if (!$chargeServiceFee || $paidQuantity === 0 || !$baseAmount->isPositive()) {
            return [
                'service_fee' => $serviceFee,
                'minimum_applied' => false,
            ];
        }

        $minimumFeePerUnit = $this->decimal((string) ($minimumUnitAmount ?? 0));

        if (!$minimumFeePerUnit->isPositive()) {
            return [
                'service_fee' => $serviceFee,
                'minimum_applied' => false,
            ];
        }

        $minimumTotalFee = $minimumFeePerUnit->multipliedBy($paidQuantity);

        if ($serviceFee->isLessThan($minimumTotalFee)) {
            return [
                'service_fee' => $minimumTotalFee,
                'minimum_applied' => true,
            ];
        }

        return [
            'service_fee' => $serviceFee,
            'minimum_applied' => false,
        ];
    }

    private function decimal(string $value): BigDecimal
    {
        return BigDecimal::of($value)->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    private function chargesServiceFee(string $paymentMethod): bool
    {
        return $paymentMethod === 'MERCADO_PAGO';
    }

    private function format(BigDecimal $value): string
    {
        return (string) $value->toScale(self::SCALE, RoundingMode::HalfUp);
    }
}
