<?php

namespace App\Services\Api\Checkout;

use App\Models\Presentation;
use App\Models\SeasonTicketType;
use App\Repositories\PresentationRepository;
use App\Repositories\TicketRepository;
use Illuminate\Validation\ValidationException;

class PresentationCapacityService
{
    public function __construct(
        private readonly PresentationRepository $presentationRepository,
        private readonly TicketRepository $ticketRepository,
    ) {
        //
    }

    public function assertCanSellQuantity(
        SeasonTicketType $ticketType,
        Presentation $presentation,
        int $quantity,
    ): void {
        if ((int) $ticketType->season_id !== (int) $presentation->season_id || ! $ticketType->is_active || $presentation->status !== 'published' || $presentation->season?->status !== 'published' || ! $presentation->starts_at->isFuture()) {
            throw ValidationException::withMessages(['presentation_id' => 'La entrada no está disponible para esta función.']);
        }
        $assignedPresentationTickets = $this->ticketRepository->countAssignedAndLockByPresentationId(
            $presentation->id
        );

        if (($assignedPresentationTickets + $quantity) > $presentation->capacity) {
            throw ValidationException::withMessages([
                'quantity' => ['presentation_capacity_exceeded'],
            ]);
        }

        if ($ticketType->stock === null) {
            return;
        }

        $assignedTicketTypeTickets = $this->ticketRepository->countAssignedAndLockByTicketTypeId($ticketType->id, $presentation->id);

        if (($assignedTicketTypeTickets + $quantity) > $ticketType->stock) {
            throw ValidationException::withMessages([
                'quantity' => ['season_ticket_type_stock_exceeded'],
            ]);
        }
    }

    public function syncFromAssignedTickets(int $presentationId): void
    {
        $presentation = $this->presentationRepository->findLockedOrFail($presentationId);
        $assignedTickets = $this->ticketRepository->countAssignedByPresentationId($presentationId);
        $remainingTickets = max(0, $presentation->capacity - $assignedTickets);

        if ($presentation->status === 'published' && $remainingTickets === 0) {
            $this->presentationRepository->update($presentation, [
                'status' => 'sold_out',
            ]);

            return;
        }

        if ($presentation->status === 'sold_out' && $remainingTickets > 0) {
            $this->presentationRepository->update($presentation, [
                'status' => 'published',
            ]);
        }
    }
}
