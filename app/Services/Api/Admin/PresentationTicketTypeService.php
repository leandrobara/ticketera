<?php

namespace App\Services\Api\Admin;

use App\Models\PresentationTicketType;
use App\Repositories\PresentationTicketTypeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PresentationTicketTypeService
{
    public function __construct(
        private readonly PresentationTicketTypeRepository $presentationTicketTypeRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->presentationTicketTypeRepository->listPaginated($filters);
    }

    public function getOne(PresentationTicketType $presentationTicketType): PresentationTicketType
    {
        return $this->presentationTicketTypeRepository->getOne($presentationTicketType);
    }

    public function create(array $data): PresentationTicketType
    {
        return $this->presentationTicketTypeRepository->store($data);
    }

    public function update(PresentationTicketType $presentationTicketType, array $data): PresentationTicketType
    {
        return $this->presentationTicketTypeRepository->update($presentationTicketType, $data);
    }

    public function delete(PresentationTicketType $presentationTicketType): void
    {
        $this->presentationTicketTypeRepository->delete($presentationTicketType);
    }
}

