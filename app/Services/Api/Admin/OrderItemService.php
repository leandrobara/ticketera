<?php

namespace App\Services\Api\Admin;

use App\Models\OrderItem;
use App\Repositories\OrderItemRepository;
use Brick\Math\BigDecimal;
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
        $data['subtotal_amount'] = $data['subtotal_amount']
            ?? $this->multiply($data['unit_price'], $data['quantity']);
        $data['discount_amount'] = $data['discount_amount'] ?? '0.000000';
        $data['total_amount'] = $data['total_amount']
            ?? $this->subtract($data['subtotal_amount'], $data['discount_amount']);

        return $this->orderItemRepository->store($data);
    }

    public function update(OrderItem $orderItem, array $data): OrderItem
    {
        $quantity = $data['quantity'] ?? $orderItem->quantity;
        $unitPrice = $data['unit_price'] ?? $orderItem->unit_price;
        $data['subtotal_amount'] = $data['subtotal_amount']
            ?? $this->multiply($unitPrice, $quantity);
        $data['discount_amount'] = $data['discount_amount']
            ?? $orderItem->discount_amount;
        $data['total_amount'] = $data['total_amount']
            ?? $this->subtract($data['subtotal_amount'], $data['discount_amount']);

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
}
