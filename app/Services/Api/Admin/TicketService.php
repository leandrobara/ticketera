<?php

namespace App\Services\Api\Admin;

use App\Models\Ticket;
use App\Repositories\TicketRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketService
{
    public function __construct(
        private readonly TicketRepository $ticketRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->ticketRepository->listPaginated($filters, $filters['per_page'] ?? 20);
    }

    public function getOne(Ticket $ticket): Ticket
    {
        return $this->ticketRepository->getOne($ticket);
    }

    public function create(array $data): Ticket
    {
        $data['code'] = $data['code'] ?? 'TCK-'.Str::upper(Str::random(10));
        $data['status'] = $data['status'] ?? 'VALID';

        return $this->ticketRepository->store($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        return $this->ticketRepository->update($ticket, $data);
    }

    public function cancel(Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($ticket) {
            $ticket = Ticket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            $ticket->update([
                'status' => 'CANCELED',
                'canceled_at' => now(),
            ]);

            resolve(OrderService::class)->syncStatusFromTickets($ticket->order);
            resolve(OrderService::class)->syncPresentationStatusFromCapacity($ticket->presentation);

            return $ticket->fresh(['order.buyer', 'orderItem', 'presentation', 'presentationTicketType']);
        });
    }

    public function markUsed(Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($ticket) {
            $ticket = Ticket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            $ticket->update([
                'status' => 'USED',
                'checked_in_at' => now(),
            ]);

            return $ticket->fresh(['order.buyer', 'orderItem', 'presentation', 'presentationTicketType']);
        });
    }

    public function delete(Ticket $ticket): void
    {
        $this->ticketRepository->delete($ticket);
    }
}
