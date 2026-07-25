<?php

namespace App\Repositories;

use App\Models\Person;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PersonRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return Person::query()
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('display_name', 'like', "{$search}%")
                        ->orWhere('normalized_name', 'like', "{$search}%")
                        ->orWhere('first_name', 'like', "{$search}%")
                        ->orWhere('last_name', 'like', "{$search}%")
                        ->orWhere('email', 'like', "{$search}%")
                        ->orWhere('document_number', 'like', "{$search}%");
                });
            })
            ->latest()
            ->paginate($limit);
    }

    public function getOne(Person $person): Person
    {
        return $person->load('showCredits.show');
    }

    public function findByEmail(string $email, ?int $ignorePersonId = null): ?Person
    {
        return Person::query()
            ->where('email', $email)
            ->when($ignorePersonId, fn ($query) => $query->where('id', '!=', $ignorePersonId))
            ->first();
    }

    public function findByDocument(string $documentType, string $documentNumber, ?int $ignorePersonId = null): ?Person
    {
        return Person::query()
            ->where('document_type', $documentType)
            ->where('document_number', $documentNumber)
            ->when($ignorePersonId, fn ($query) => $query->where('id', '!=', $ignorePersonId))
            ->first();
    }

    public function findCandidates(array $filters, ?int $ignorePersonId = null): Collection
    {
        return Person::query()
            ->when($ignorePersonId, fn ($query) => $query->where('id', '!=', $ignorePersonId))
            ->where(function ($query) use ($filters) {
                if ($filters['email'] ?? null) {
                    $query->orWhere('email', $filters['email']);
                }

                if (($filters['document_type'] ?? null) && ($filters['document_number'] ?? null)) {
                    $query->orWhere(function ($query) use ($filters) {
                        $query
                            ->where('document_type', $filters['document_type'])
                            ->where('document_number', $filters['document_number']);
                    });
                }

                if ($filters['normalized_name'] ?? null) {
                    $query->orWhere('normalized_name', $filters['normalized_name']);
                }
            })
            ->latest()
            ->limit(10)
            ->get();
    }

    public function store(array $attrs): Person
    {
        return Person::create($attrs);
    }

    public function update(Person $person, array $attrs): Person
    {
        $person->update($attrs);
        return $person->fresh();
    }

    public function delete(Person $person): void
    {
        $person->delete();
    }
}
