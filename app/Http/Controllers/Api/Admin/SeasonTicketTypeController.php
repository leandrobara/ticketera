<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreateSeasonTicketTypeRequest;
use App\Http\Requests\Admin\DeleteSeasonTicketTypeRequest;
use App\Http\Requests\Admin\GetSeasonTicketTypeRequest;
use App\Http\Requests\Admin\ListSeasonTicketTypeRequest;
use App\Http\Requests\Admin\UpdateSeasonTicketTypeRequest;
use App\Models\SeasonTicketType;
use App\Services\Api\Admin\SeasonTicketTypeService;

class SeasonTicketTypeController extends BaseAPIController
{
    public function list(ListSeasonTicketTypeRequest $req): array
    {
        $ticketTypeList = resolve(SeasonTicketTypeService::class)->list($req->validated());

        return $this->getSuccessResponse($ticketTypeList);
    }

    public function create(CreateSeasonTicketTypeRequest $req): array
    {
        $ticketType = resolve(SeasonTicketTypeService::class)->create($req->validated());

        return $this->getSuccessResponse($ticketType);
    }

    public function show(SeasonTicketType $seasonTicketType, GetSeasonTicketTypeRequest $req): array
    {
        $ticketType = resolve(SeasonTicketTypeService::class)->getOne($seasonTicketType);

        return $this->getSuccessResponse($ticketType);
    }

    public function update(SeasonTicketType $seasonTicketType, UpdateSeasonTicketTypeRequest $req): array
    {
        $ticketType = resolve(SeasonTicketTypeService::class)->update($seasonTicketType, $req->validated());

        return $this->getSuccessResponse($ticketType);
    }

    public function delete(SeasonTicketType $seasonTicketType, DeleteSeasonTicketTypeRequest $req): array
    {
        resolve(SeasonTicketTypeService::class)->delete($seasonTicketType);

        return $this->getSuccessResponse($seasonTicketType);
    }
}
