<?php

namespace App\Services\Api\Admin;

use App\Models\ShowPerformanceHistory;
use App\Repositories\ShowPerformanceHistoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShowPerformanceHistoryService
{
    public function __construct(
        private readonly ShowPerformanceHistoryRepository $repository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->listPaginated($filters, $filters['per_page'] ?? 100);
    }

    public function getOne(ShowPerformanceHistory $history): ShowPerformanceHistory
    {
        return $this->repository->getOne($history);
    }

    public function create(array $data): ShowPerformanceHistory
    {
        $data['sort_order'] = $data['sort_order'] ?? 1;

        return $this->repository->store($data);
    }

    public function update(ShowPerformanceHistory $history, array $data): ShowPerformanceHistory
    {
        return $this->repository->update($history, $data);
    }

    public function delete(ShowPerformanceHistory $history): void
    {
        $this->repository->delete($history);
    }
}
