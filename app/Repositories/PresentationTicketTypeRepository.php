<?php

namespace App\Repositories;

use App\Models\PresentationTicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PresentationTicketTypeRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return PresentationTicketType::query()
            ->with('presentation')
            ->when($filters['presentation_id'] ?? null, function ($query, int $presentationId) {
                $query->where('presentation_id', $presentationId);
            })
            ->when(array_key_exists('is_active', $filters), function ($query) use ($filters) {
                $query->where('is_active', $filters['is_active']);
            })
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($limit);
    }

    public function getOne(PresentationTicketType $presentationTicketType): PresentationTicketType
    {
        return $presentationTicketType->load('presentation');
    }

    public function store(array $attrs): PresentationTicketType
    {
        $ticketType = PresentationTicketType::create($attrs);
        return $ticketType->load('presentation');
    }

    public function update(PresentationTicketType $presentationTicketType, array $attrs): PresentationTicketType
    {
        $presentationTicketType->update($attrs);
        return $presentationTicketType->fresh('presentation');
    }

    public function delete(PresentationTicketType $presentationTicketType): void
    {
        $presentationTicketType->delete();
    }
}

