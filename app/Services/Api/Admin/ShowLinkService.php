<?php

namespace App\Services\Api\Admin;

use App\Models\ShowLink;
use App\Repositories\ShowLinkRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShowLinkService
{
    public function __construct(
        private readonly ShowLinkRepository $repository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->listPaginated($filters, $filters['per_page'] ?? 100);
    }

    public function getOne(ShowLink $showLink): ShowLink
    {
        return $this->repository->getOne($showLink);
    }

    public function create(array $data): ShowLink
    {
        $data['sort_order'] = $data['sort_order'] ?? 1;

        return $this->repository->store($data);
    }

    public function update(ShowLink $showLink, array $data): ShowLink
    {
        return $this->repository->update($showLink, $data);
    }

    public function delete(ShowLink $showLink): void
    {
        $this->repository->delete($showLink);
    }
}
