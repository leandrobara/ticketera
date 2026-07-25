<?php

namespace App\Repositories;

use App\Models\ShowCredit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShowCreditRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return ShowCredit::query()
            ->with(['show', 'person'])
            ->when($filters['show_id'] ?? null, fn ($query, int $showId) => $query->where('show_id', $showId))
            ->when($filters['person_id'] ?? null, fn ($query, int $personId) => $query->where('person_id', $personId))
            ->when($filters['role_label'] ?? null, fn ($query, string $roleLabel) => $query->where('role_label', $roleLabel))
            ->when($filters['section'] ?? null, fn ($query, string $section) => $query->where('section', $section))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('character_name', 'like', "%{$search}%")
                        ->orWhere('display_name_override', 'like', "%{$search}%")
                        ->orWhere('role_label', 'like', "%{$search}%")
                        ->orWhereHas('person', fn ($query) => $query->where('display_name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('show_id')
            ->orderBy('section')
            ->orderBy('sort_order')
            ->paginate($limit);
    }

    public function getOne(ShowCredit $showCredit): ShowCredit
    {
        return $showCredit->load(['show', 'person']);
    }

    public function findDuplicate(array $attrs, ?int $ignoreShowCreditId = null): ?ShowCredit
    {
        return ShowCredit::query()
            ->where('show_id', $attrs['show_id'])
            ->where('section', $attrs['section'])
            ->where('role_label', $attrs['role_label'])
            ->where('character_name', $attrs['character_name'] ?? null)
            ->when(
                filled($attrs['person_id'] ?? null),
                fn ($query) => $query->where('person_id', $attrs['person_id']),
                fn ($query) => $query
                    ->whereNull('person_id')
                    ->where('display_name_override', $attrs['display_name_override'] ?? null)
            )
            ->when($ignoreShowCreditId, fn ($query) => $query->where('id', '!=', $ignoreShowCreditId))
            ->first();
    }

    public function store(array $attrs): ShowCredit
    {
        return ShowCredit::create($attrs)->load(['show', 'person']);
    }

    public function update(ShowCredit $showCredit, array $attrs): ShowCredit
    {
        $showCredit->update($attrs);
        return $this->getOne($showCredit->fresh());
    }

    public function delete(ShowCredit $showCredit): void
    {
        $showCredit->delete();
    }
}
