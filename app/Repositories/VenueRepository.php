<?php

namespace App\Repositories;

use App\Models\Venue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VenueRepository
{
    public function listPaginated(?string $search, int $limit = 20): LengthAwarePaginator
    {
        return Venue::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('neighborhood', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($limit);
    }

    public function store(array $attrs): Venue
    {
        return Venue::create($attrs);
    }

    public function update(Venue $venue, array $attrs): Venue
    {
        $venue->update($attrs);
        return $venue->fresh();
    }

    public function delete(Venue $venue): void
    {
        $venue->delete();
    }
}

