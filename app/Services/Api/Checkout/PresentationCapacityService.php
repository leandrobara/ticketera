<?php

namespace App\Services\Api\Checkout;

use App\Repositories\PresentationRepository;
use App\Repositories\TicketRepository;

class PresentationCapacityService
{
    public function __construct(
        private readonly PresentationRepository $presentationRepository,
        private readonly TicketRepository $ticketRepository,
    ) {
        //
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
