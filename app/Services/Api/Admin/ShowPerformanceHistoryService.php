<?php

namespace App\Services\Api\Admin;

use App\Helpers\RedisHelper;
use App\Models\ShowPerformanceHistory;
use App\Repositories\ShowPerformanceHistoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShowPerformanceHistoryService
{
    public function __construct(
        private readonly ShowPerformanceHistoryRepository $repository,
        private readonly RedisHelper $redisHelper,
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

        $history = $this->repository->store($data);
        $this->redisHelper->deleteByPartialKey('site:show:'.$history->show_id.':getPublicShow');

        return $history;
    }

    public function update(ShowPerformanceHistory $history, array $data): ShowPerformanceHistory
    {
        $previousShowId = $history->show_id;
        $history = $this->repository->update($history, $data);
        $this->redisHelper->deleteByPartialKey('site:show:'.$previousShowId.':getPublicShow');
        $this->redisHelper->deleteByPartialKey('site:show:'.$history->show_id.':getPublicShow');

        return $history;
    }

    public function delete(ShowPerformanceHistory $history): void
    {
        $this->repository->delete($history);
        $this->redisHelper->deleteByPartialKey('site:show:'.$history->show_id.':getPublicShow');
    }
}
