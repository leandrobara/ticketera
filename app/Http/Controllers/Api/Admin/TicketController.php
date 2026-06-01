<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateTicketRequest;
use App\Http\Requests\Admin\DeleteTicketRequest;
use App\Http\Requests\Admin\GetTicketRequest;
use App\Http\Requests\Admin\ListTicketRequest;
use App\Http\Requests\Admin\UpdateTicketRequest;
use App\Models\Ticket;
use App\Services\Api\Admin\TicketService;

class TicketController extends BaseAPIController
{
    public function list(ListTicketRequest $req): array
    {
        return $this->getSuccessResponse(resolve(TicketService::class)->list($req->validated()));
    }

    public function create(CreateTicketRequest $req): array
    {
        return $this->getSuccessResponse(resolve(TicketService::class)->create($req->validated()));
    }

    public function show(Ticket $ticket, GetTicketRequest $req): array
    {
        return $this->getSuccessResponse(resolve(TicketService::class)->getOne($ticket));
    }

    public function update(Ticket $ticket, UpdateTicketRequest $req): array
    {
        return $this->getSuccessResponse(resolve(TicketService::class)->update($ticket, $req->validated()));
    }

    public function delete(Ticket $ticket, DeleteTicketRequest $req): array
    {
        resolve(TicketService::class)->delete($ticket);
        return $this->getSuccessResponse($ticket);
    }
}

