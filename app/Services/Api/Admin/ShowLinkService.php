<?php

namespace App\Services\Api\Admin;

use App\Helpers\RedisHelper;
use App\Models\ShowLink;
use App\Repositories\ShowLinkRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShowLinkService
{
    public function __construct(
        private readonly ShowLinkRepository $repository,
        private readonly RedisHelper $redisHelper,
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

        $showLink = $this->repository->store($data);
        $this->redisHelper->deleteByPartialKey('site:show:'.$showLink->show_id.':getPublicShow');

        return $showLink;
    }

    public function update(ShowLink $showLink, array $data): ShowLink
    {
        $previousShowId = $showLink->show_id;
        $showLink = $this->repository->update($showLink, $data);
        $this->redisHelper->deleteByPartialKey('site:show:'.$previousShowId.':getPublicShow');
        $this->redisHelper->deleteByPartialKey('site:show:'.$showLink->show_id.':getPublicShow');

        return $showLink;
    }

    public function delete(ShowLink $showLink): void
    {
        $this->repository->delete($showLink);
        $this->redisHelper->deleteByPartialKey('site:show:'.$showLink->show_id.':getPublicShow');
    }
}
