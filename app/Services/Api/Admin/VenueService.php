<?php

namespace App\Services\Api\Admin;

use App\Helpers\RedisHelper;
use App\Models\Venue;
use App\Repositories\SeasonRepository;
use App\Repositories\VenueRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VenueService
{
    public function __construct(
        private readonly VenueRepository $venueRepository,
        private readonly SeasonRepository $seasonRepository,
        private readonly RedisHelper $redisHelper,
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
        $venue = $this->venueRepository->update($venue, $data);

        foreach ($this->seasonRepository->getIdsByVenueId($venue->id) as $seasonId) {
            $this->redisHelper->deleteByPartialKey('site:season:'.$seasonId.':getVenue');
        }

        return $venue;
    }

    public function delete(Venue $venue): void
    {
        $seasonIds = $this->seasonRepository->getIdsByVenueId($venue->id);
        $this->venueRepository->delete($venue);

        foreach ($seasonIds as $seasonId) {
            $this->redisHelper->deleteByPartialKey('site:season:'.$seasonId.':getVenue');
        }
    }
}
