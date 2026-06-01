<?php

namespace App\Services\Api\Admin;

use App\Models\Presentation;
use App\Repositories\PresentationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PresentationService
{
    public function __construct(
        private readonly PresentationRepository $presentationRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->presentationRepository->listPaginated($filters);
    }

    public function getOne(Presentation $presentation): Presentation
    {
        return $this->presentationRepository->getOne($presentation);
    }

    public function create(array $data): Presentation
    {
        return $this->presentationRepository->store($data);
    }

    public function update(Presentation $presentation, array $data): Presentation
    {
        return $this->presentationRepository->update($presentation, $data);
    }

    public function delete(Presentation $presentation): void
    {
        $this->presentationRepository->delete($presentation);
    }
}

