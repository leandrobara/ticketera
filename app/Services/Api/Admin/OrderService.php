<?php

namespace App\Services\Api\Admin;

use App\Models\Order;
use App\Repositories\OrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->orderRepository->listPaginated($filters);
    }

    public function getOne(Order $order): Order
    {
        return $this->orderRepository->getOne($order);
    }

    public function create(array $data): Order
    {
        $data['code'] = $data['code'] ?? 'ORD-'.Str::upper(Str::random(10));
        $data['currency'] = $data['currency'] ?? 'ARS';

        return $this->orderRepository->store($data);
    }

    public function update(Order $order, array $data): Order
    {
        return $this->orderRepository->update($order, $data);
    }

    public function delete(Order $order): void
    {
        $this->orderRepository->delete($order);
    }
}
