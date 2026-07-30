<?php

namespace App\Services\Api\Admin;

use App\Helpers\RedisHelper;
use App\Models\Show;
use App\Repositories\ShowRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ShowService
{
    public function __construct(
        private readonly ShowRepository $showRepository,
        private readonly RedisHelper $redisHelper,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->showRepository->listPaginated(
            $filters['search'] ?? null,
            $filters['per_page'] ?? 20,
        );
    }

    public function getOne(Show $show): Show
    {
        return $show;
    }

    public function create(array $data): Show
    {
        if (blank($data['slug'] ?? null)) {
            $data['slug'] = Str::slug($data['title']);
        }

        return $this->showRepository->store($data);
    }

    public function update(Show $show, array $data): Show
    {
        $show = $this->showRepository->update($show, $data);
        $this->redisHelper->deleteByPartialKey('site:show:'.$show->id.':getPublicShow');

        return $show;
    }

    public function delete(Show $show): void
    {
        $this->showRepository->delete($show);
        $this->redisHelper->deleteByPartialKey('site:show:'.$show->id.':getPublicShow');
    }
}
