<?php

namespace App\Services\Api\Admin;

use App\Models\OrderItem;
use App\Repositories\OrderItemRepository;
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
        $data['total_amount'] = $data['total_amount'] ?? ($data['quantity'] * $data['unit_price']);

        return $this->orderItemRepository->store($data);
    }

    public function update(OrderItem $orderItem, array $data): OrderItem
    {
        if (! array_key_exists('total_amount', $data)) {
            $quantity = $data['quantity'] ?? $orderItem->quantity;
            $unitPrice = $data['unit_price'] ?? $orderItem->unit_price;
            $data['total_amount'] = $quantity * $unitPrice;
        }

        return $this->orderItemRepository->update($orderItem, $data);
    }

    public function delete(OrderItem $orderItem): void
    {
        $this->orderItemRepository->delete($orderItem);
    }
}
