<?php

namespace App\Services\Api\Admin;

use App\Models\Venue;
use App\Repositories\VenueRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VenueService
{
    public function __construct(
        private readonly VenueRepository $venueRepository,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->venueRepository->listPaginated($filters['search'] ?? null);
    }

    public function getOne(Venue $venue): Venue
    {
        return $venue;
    }

    public function create(array $data): Venue
    {
        return $this->venueRepository->store($data);
    }

    public function update(Venue $venue, array $data): Venue
    {
        return $this->venueRepository->update($venue, $data);
    }

    public function delete(Venue $venue): void
    {
        $this->venueRepository->delete($venue);
    }
}

