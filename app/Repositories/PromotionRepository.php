<?php

namespace App\Repositories;

use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PromotionRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return Promotion::query()
            ->with(['presentationTicketType.presentation.show'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('access_code', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when(array_key_exists('is_active', $filters), function ($query) use ($filters) {
                $query->where('is_active', $filters['is_active']);
            })
            ->when(($filters['access'] ?? null) === 'public', fn ($query) => $query->whereNull('access_code'))
            ->when(($filters['access'] ?? null) === 'code', fn ($query) => $query->whereNotNull('access_code'))
            ->latest()
            ->paginate($limit);
    }

    public function getOne(Promotion $promotion): Promotion
    {
        return $promotion->load(['presentationTicketType.presentation.show']);
    }

    public function store(array $attrs): Promotion
    {
        return $this->getOne(Promotion::create($attrs));
    }

    public function update(Promotion $promotion, array $attrs): Promotion
    {
        $promotion->update($attrs);
        return $this->getOne($promotion->fresh());
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }
}
