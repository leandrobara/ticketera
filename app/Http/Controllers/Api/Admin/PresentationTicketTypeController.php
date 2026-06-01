<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Admin\CreatePresentationTicketTypeRequest;
use App\Http\Requests\Admin\DeletePresentationTicketTypeRequest;
use App\Http\Requests\Admin\GetPresentationTicketTypeRequest;
use App\Http\Requests\Admin\ListPresentationTicketTypeRequest;
use App\Http\Requests\Admin\UpdatePresentationTicketTypeRequest;
use App\Models\PresentationTicketType;
use App\Services\Api\Admin\PresentationTicketTypeService;

class PresentationTicketTypeController extends BaseAPIController
{
    public function list(ListPresentationTicketTypeRequest $req): array
    {
        $ticketTypeList = resolve(PresentationTicketTypeService::class)->list($req->validated());
        return $this->getSuccessResponse($ticketTypeList);
    }

    public function create(CreatePresentationTicketTypeRequest $req): array
    {
        $ticketType = resolve(PresentationTicketTypeService::class)->create($req->validated());
        return $this->getSuccessResponse($ticketType);
    }

    public function show(PresentationTicketType $presentationTicketType, GetPresentationTicketTypeRequest $req): array
    {
        $ticketType = resolve(PresentationTicketTypeService::class)->getOne($presentationTicketType);
        return $this->getSuccessResponse($ticketType);
    }

    public function update(PresentationTicketType $presentationTicketType, UpdatePresentationTicketTypeRequest $req): array
    {
        $ticketType = resolve(PresentationTicketTypeService::class)->update($presentationTicketType, $req->validated());
        return $this->getSuccessResponse($ticketType);
    }

    public function delete(PresentationTicketType $presentationTicketType, DeletePresentationTicketTypeRequest $req): array
    {
        resolve(PresentationTicketTypeService::class)->delete($presentationTicketType);
        return $this->getSuccessResponse($presentationTicketType);
    }
}

