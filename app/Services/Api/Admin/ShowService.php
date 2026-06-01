<?php

namespace App\Services\Api\Admin;

use App\Models\Show;
use Illuminate\Support\Str;
use App\Repositories\ShowRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;


class ShowService
{

    public function __construct(
        private readonly ShowRepository $showRepository,
    ) {
        //
    }


    public function list(array $filters): LengthAwarePaginator
    {
        return $this->showRepository->listPaginated($filters['search'] ?? null);
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

        $publishedAt = $data['status'] === 'published' ? now() : null;
        $data['published_at'] = $publishedAt;

        return $this->showRepository->store($data);
    }


    public function update(Show $show, array $data): Show
    {
        $status = $data['status'] ?? null;

        if ($status == 'draft') {
            $data['published_at'] = null;
        }
            
        if ($status == 'published') {
            $data['published_at'] = now();
        }

        return $this->showRepository->update($show, $data);
    }


    public function delete(Show $show): void
    {
        $this->showRepository->delete($show);
    }
}
