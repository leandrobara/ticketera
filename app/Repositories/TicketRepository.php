<?php

namespace App\Repositories;

use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TicketRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return Ticket::query()
            ->with(['order.buyer', 'orderItem', 'presentation', 'presentationTicketType'])
            ->when($filters['order_id'] ?? null, fn ($query, int $orderId) => $query->where('order_id', $orderId))
            ->when($filters['presentation_id'] ?? null, fn ($query, int $presentationId) => $query->where('presentation_id', $presentationId))
            ->when($filters['presentation_ticket_type_id'] ?? null, fn ($query, int $ticketTypeId) => $query->where('presentation_ticket_type_id', $ticketTypeId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('code', 'like', "%{$search}%"))
            ->latest()
            ->paginate($limit);
    }

    public function countLockedByOrderItemId(int $orderItemId): int
    {
        return Ticket::query()
            ->where('order_item_id', $orderItemId)
            ->lockForUpdate()
            ->count();
    }

    public function countByOrderId(int $orderId): int
    {
        return Ticket::query()
            ->where('order_id', $orderId)
            ->count();
    }

    public function countAssignedByPresentationId(int $presentationId): int
    {
        return Ticket::query()
            ->where('presentation_id', $presentationId)
            ->whereIn('status', ['VALID', 'USED'])
            ->count();
    }

    public function existsByCode(string $code): bool
    {
        return Ticket::query()
            ->where('code', $code)
            ->exists();
    }

    public function getOne(Ticket $ticket): Ticket
    {
        return $ticket->load(['order.buyer', 'orderItem', 'presentation', 'presentationTicketType']);
    }

    public function store(array $attrs): Ticket
    {
        return Ticket::create($attrs)->load(['order.buyer', 'orderItem', 'presentation', 'presentationTicketType']);
    }

    public function update(Ticket $ticket, array $attrs): Ticket
    {
        $ticket->update($attrs);
        return $ticket->fresh(['order.buyer', 'orderItem', 'presentation', 'presentationTicketType']);
    }

    public function delete(Ticket $ticket): void
    {
        $ticket->delete();
    }
}
