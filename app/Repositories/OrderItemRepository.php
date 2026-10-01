<?php

namespace App\Repositories;

use App\Models\OrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderItemRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return OrderItem::query()
            ->with(['order', 'seasonTicketType'])
            ->when($filters['order_id'] ?? null, fn ($query, int $orderId) => $query->where('order_id', $orderId))
            ->when($filters['season_ticket_type_id'] ?? null, fn ($query, int $ticketTypeId) => $query->where('season_ticket_type_id', $ticketTypeId))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->latest()
            ->paginate($limit);
    }

    public function getOne(OrderItem $orderItem): OrderItem
    {
        return $orderItem->load(['order', 'seasonTicketType']);
    }

    public function store(array $attrs): OrderItem
    {
        return OrderItem::create($attrs)->load(['order', 'seasonTicketType']);
    }

    public function update(OrderItem $orderItem, array $attrs): OrderItem
    {
        $orderItem->update($attrs);

        return $orderItem->fresh(['order', 'seasonTicketType']);
    }

    public function delete(OrderItem $orderItem): void
    {
        $orderItem->delete();
    }
}
