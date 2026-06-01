<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return Order::query()
            ->with(['buyer', 'presentation', 'items', 'tickets'])
            ->when($filters['buyer_id'] ?? null, fn ($query, int $buyerId) => $query->where('buyer_id', $buyerId))
            ->when($filters['presentation_id'] ?? null, fn ($query, int $presentationId) => $query->where('presentation_id', $presentationId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['payment_method'] ?? null, fn ($query, string $paymentMethod) => $query->where('payment_method', $paymentMethod))
            ->when($filters['source'] ?? null, fn ($query, string $source) => $query->where('source', $source))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhereHas('buyer', function ($query) use ($search) {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($limit);
    }

    public function getOne(Order $order): Order
    {
        return $order->load(['buyer', 'presentation', 'createdByUser', 'items', 'tickets', 'payments']);
    }

    public function store(array $attrs): Order
    {
        return Order::create($attrs)->load(['buyer', 'presentation']);
    }

    public function update(Order $order, array $attrs): Order
    {
        $order->update($attrs);
        return $order->fresh(['buyer', 'presentation', 'items', 'tickets', 'payments']);
    }

    public function delete(Order $order): void
    {
        $order->delete();
    }
}
