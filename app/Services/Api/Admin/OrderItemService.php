<?php

namespace App\Services\Api\Admin;

use App\Models\OrderItem;
use App\Repositories\OrderItemRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderItemService
{
    public function __construct(
        private readonly OrderItemRepository $orderItemRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->orderItemRepository->listPaginated($filters);
    }

    public function getOne(OrderItem $orderItem): OrderItem
    {
        return $this->orderItemRepository->getOne($orderItem);
    }

    public function create(array $data): OrderItem
    {
        $data['paid_quantity'] = $data['paid_quantity'] ?? $data['quantity'];
        $data['subtotal_amount'] = $data['subtotal_amount']
            ?? $this->multiply($data['unit_price'], $data['quantity']);
        $data['discount_amount'] = $data['discount_amount'] ?? '0.000000';
        $data['service_fee_base_amount'] = $data['service_fee_base_amount']
            ?? $this->subtract($data['subtotal_amount'], $data['discount_amount']);
        $data['service_fee_total_amount'] = $data['service_fee_total_amount']
            ?? $this->calculateServiceFeeTotal($data, $data['service_fee_base_amount'], $data['paid_quantity']);
        $data['unit_service_fee'] = $data['unit_service_fee']
            ?? $this->divide($data['service_fee_total_amount'], $data['paid_quantity']);
        $data['total_amount'] = $data['total_amount']
            ?? $this->sum($data['service_fee_base_amount'], $data['service_fee_total_amount']);

        return $this->orderItemRepository->store($data);
    }

    public function update(OrderItem $orderItem, array $data): OrderItem
    {
        $quantity = $data['quantity'] ?? $orderItem->quantity;
        $paidQuantity = $data['paid_quantity'] ?? $orderItem->paid_quantity;
        $unitPrice = $data['unit_price'] ?? $orderItem->unit_price;
        $data['subtotal_amount'] = $data['subtotal_amount']
            ?? $this->multiply($unitPrice, $quantity);
        $data['discount_amount'] = $data['discount_amount']
            ?? $orderItem->discount_amount;
        $data['service_fee_type'] = $data['service_fee_type'] ?? $orderItem->service_fee_type;
        $data['service_fee_fixed_amount'] = $data['service_fee_fixed_amount'] ?? $orderItem->service_fee_fixed_amount;
        $data['service_fee_percentage'] = $data['service_fee_percentage'] ?? $orderItem->service_fee_percentage;
        $data['service_fee_base_amount'] = $data['service_fee_base_amount']
            ?? $this->subtract($data['subtotal_amount'], $data['discount_amount']);
        $data['service_fee_total_amount'] = $data['service_fee_total_amount']
            ?? $this->calculateServiceFeeTotal($data, $data['service_fee_base_amount'], $paidQuantity);
        $data['unit_service_fee'] = $data['unit_service_fee']
            ?? $this->divide($data['service_fee_total_amount'], $paidQuantity);
        $data['total_amount'] = $data['total_amount']
            ?? $this->sum($data['service_fee_base_amount'], $data['service_fee_total_amount']);

        return $this->orderItemRepository->update($orderItem, $data);
    }

    public function delete(OrderItem $orderItem): void
    {
        $this->orderItemRepository->delete($orderItem);
    }

    private function multiply(string $amount, int $quantity): string
    {
        return (string) BigDecimal::of($amount)
            ->multipliedBy($quantity)
            ->toScale(6);
    }

    private function subtract(string $subtotal, string $discount): string
    {
        return (string) BigDecimal::of($subtotal)
            ->minus($discount)
            ->toScale(6);
    }

    private function sum(string $amount, string $fee): string
    {
        return (string) BigDecimal::of($amount)
            ->plus($fee)
            ->toScale(6);
    }

    private function divide(string $amount, int $quantity): string
    {
        if ($quantity === 0) {
            return '0.000000';
        }

        return (string) BigDecimal::of($amount)
            ->dividedBy($quantity, 6, RoundingMode::HalfUp);
    }

    private function calculateServiceFeeTotal(array $data, string $baseAmount, int $paidQuantity): string
    {
        if (($data['service_fee_type'] ?? null) === 'percentage') {
            return (string) BigDecimal::of($baseAmount)
                ->multipliedBy($data['service_fee_percentage'] ?? 0)
                ->dividedBy(100, 6, RoundingMode::HalfUp);
        }

        if (($data['service_fee_type'] ?? null) === 'fixed_amount') {
            return $this->multiply(
                (string) ($data['service_fee_fixed_amount'] ?? 0),
                $paidQuantity,
            );
        }

        return $this->multiply(
            (string) ($data['unit_service_fee'] ?? 0),
            $paidQuantity,
        );
    }
}
