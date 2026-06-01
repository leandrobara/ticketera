<?php

namespace App\Services\Api\Admin;

use App\Models\Ticket;
use App\Repositories\TicketRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        return $this->ticketRepository->listPaginated($filters);
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

    public function delete(Ticket $ticket): void
    {
        $this->ticketRepository->delete($ticket);
    }
}
