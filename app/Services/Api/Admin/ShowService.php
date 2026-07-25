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
        return $this->showRepository->update($show, $data);
    }


    public function delete(Show $show): void
    {
        $this->showRepository->delete($show);
    }
}
