<?php

namespace App\Repositories;

use App\Models\Show;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShowRepository
{

    public function listPaginated(?string $search, int $limit = 20): LengthAwarePaginator
    {
        return Show::query()->latest()->paginate($limit);
    }

    
    public function store(array $attrs): Show
    {
        return Show::create($attrs);
    }

    
    public function update(Show $show, array $attrs): Show
    {
        $show->update($attrs);
        return $show->fresh();
    }

    
    public function delete(Show $show): void
    {
        $show->delete();
    }
}
