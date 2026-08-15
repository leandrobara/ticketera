<?php

namespace App\Services\Api\Checkout;

use App\Models\Order;
use App\Repositories\TicketRepository;
use Illuminate\Support\Str;

class TicketService
{
    public function __construct(
        private readonly TicketRepository $ticketRepository,
    ) {
        //
    }

    public function createMissingTicketsForOrder(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $existingTicketsCount = $this->ticketRepository->countLockedByOrderItemId($item->id);
            $missingTicketsCount = max(0, $item->quantity - $existingTicketsCount);

            if ($missingTicketsCount === 0) {
                continue;
            }

            for ($i = 0; $i < $missingTicketsCount; $i++) {
                $this->ticketRepository->store([
                    'status' => 'VALID',
                    'order_id' => $order->id,
                    'show_id' => $order->show_id,
                    'order_item_id' => $item->id,
                    'code' => $this->makeUniqueCode(),
                    'presentation_id' => $order->presentation_id,
                    'presentation_ticket_type_id' => $item->presentation_ticket_type_id,
                ]);
            }
        }
    }

    public function orderHasAllTickets(Order $order): bool
    {
        return $this->ticketRepository->countByOrderId($order->id) >= $order->total_quantity;
    }

    private function makeUniqueCode(): string
    {
        do {
            $code = 'TCK-'.Str::upper(Str::random(10));
        } while ($this->ticketRepository->existsByCode($code));

        return $code;
    }
}
