<?php

namespace App\Services\Api\Admin;

use App\Models\Order;
use App\Models\Presentation;
use App\Models\SeasonTicketType;
use App\Models\Ticket;
use App\Repositories\TicketRepository;
use App\Services\Api\Checkout\PresentationCapacityService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        return DB::transaction(function () use ($data) {
            $type = SeasonTicketType::lockForUpdate()->findOrFail($data['season_ticket_type_id'] ?? null);
            $presentation = Presentation::lockForUpdate()->findOrFail($data['presentation_id']);
            $order = Order::lockForUpdate()->findOrFail($data['order_id']);
            $item = $order->items()->lockForUpdate()->findOrFail($data['order_item_id']);
            if ($order->status !== 'APPROVED' || Ticket::withTrashed()->where('order_item_id', $item->id)->count() >= $item->quantity || ($data['status'] ?? 'VALID') !== 'VALID') {
                throw ValidationException::withMessages(['order_item_id' => 'La emisión debe corresponder a entradas pendientes de una orden aprobada.']);
            }
            app(PresentationCapacityService::class)->assertCanSellQuantity($type, $presentation, 1);
            $data['code'] = $data['code'] ?? 'TCK-'.Str::upper(Str::random(10));
            $data['status'] = 'VALID';
            $ticket = $this->ticketRepository->store($data);
            resolve(OrderService::class)->syncPresentationStatusFromCapacity($presentation);

            return $ticket;
        });
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        foreach (['order_id', 'order_item_id', 'presentation_id', 'season_ticket_type_id'] as $key) {
            if (array_key_exists($key, $data) && (int) $data[$key] !== (int) $ticket->$key) {
                throw ValidationException::withMessages([$key => 'No se puede cambiar el destino de una entrada emitida.']);
            }
        }
        if (isset($data['status']) && $data['status'] !== $ticket->status) {
            if ($data['status'] === 'CANCELED') {
                return $this->cancel($ticket);
            }
            if ($data['status'] === 'USED') {
                return $this->markUsed($ticket);
            }
            throw ValidationException::withMessages(['status' => 'No se puede reactivar una entrada cancelada o ya utilizada.']);
        }

        return $this->ticketRepository->update($ticket, $data);
    }

    public function cancel(Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($ticket) {
            $ticket = Ticket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            if ($ticket->status === 'CANCELED') {
                return $ticket->fresh(['order.buyer', 'orderItem', 'presentation', 'seasonTicketType']);
            }
            $ticket->update([
                'status' => 'CANCELED',
                'canceled_at' => now(),
            ]);

            resolve(OrderService::class)->syncStatusFromTickets($ticket->order);
            resolve(OrderService::class)->syncPresentationStatusFromCapacity($ticket->presentation);

            return $ticket->fresh(['order.buyer', 'orderItem', 'presentation', 'seasonTicketType']);
        });
    }

    public function markUsed(Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($ticket) {
            $ticket = Ticket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            if ($ticket->status !== 'VALID' || $ticket->order?->status !== 'APPROVED') {
                throw ValidationException::withMessages(['ticket' => 'Solo se pueden validar entradas vigentes de órdenes aprobadas.']);
            }
            $ticket->update([
                'status' => 'USED',
                'checked_in_at' => now(),
            ]);

            return $ticket->fresh(['order.buyer', 'orderItem', 'presentation', 'seasonTicketType']);
        });
    }

    public function delete(Ticket $ticket): void
    {
        DB::transaction(function () use ($ticket) {
            $ticket = $this->cancel($ticket);
            $this->ticketRepository->delete($ticket);
        });
    }
}
