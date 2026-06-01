<?php

namespace App\Repositories;

use App\Models\Buyer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuyerRepository
{
    public function findByEmail(string $email): ?Buyer
    {
        return Buyer::query()
            ->where('email', $email)
            ->first();
    }

    public function findByEmailWithTrashed(string $email): ?Buyer
    {
        return Buyer::withTrashed()->where('email', $email)->first();
    }

    public function listPaginated(?string $search, int $limit = 20): LengthAwarePaginator
    {
        return Buyer::query()
            ->when($search, function ($query) use ($search) {
                $normalizedSearch = mb_strtolower(trim($search));
                $phoneSearch = preg_replace('/\D+/', '', $search);

                $query->where(function ($query) use ($normalizedSearch, $phoneSearch) {
                    $query
                        ->where('name', 'like', "%{$normalizedSearch}%")
                        ->orWhere('email', 'like', "%{$normalizedSearch}%");

                    if (! blank($phoneSearch)) {
                        $query->orWhere('phone', 'like', "%{$phoneSearch}%");
                    }
                });
            })
            ->latest()
            ->paginate($limit);
    }

    public function store(array $attrs): Buyer
    {
        return Buyer::create($attrs);
    }

    public function update(Buyer $buyer, array $attrs): Buyer
    {
        $buyer->update($attrs);
        return $buyer->fresh();
    }

    public function restore(Buyer $buyer): Buyer
    {
        $buyer->restore();
        return $buyer->fresh();
    }

    public function delete(Buyer $buyer): void
    {
        $buyer->delete();
    }
}
