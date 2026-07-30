<?php

namespace App\Services\Api\Admin;

use App\Helpers\RedisHelper;
use App\Models\Season;
use App\Repositories\SeasonRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeasonService
{
    public function __construct(
        private readonly SeasonRepository $seasonRepository,
        private readonly RedisHelper $redisHelper,
    ) {
        //
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->seasonRepository->listPaginated(
            $filters,
            $filters['per_page'] ?? 20,
        );
    }

    public function getOne(Season $season): Season
    {
        return $this->seasonRepository->getOne($season);
    }

    public function create(array $data): Season
    {
        $data['closed_season_id'] = 0;
        $data['published_at'] = $data['status'] === 'published' ? now() : null;

        try {
            return $this->seasonRepository->store($data);
        } catch (QueryException $exception) {
            $this->throwFriendlyUniqueException($exception);
            throw $exception;
        }
    }

    public function update(Season $season, array $data): Season
    {
        $updatedSeason = DB::transaction(function () use ($season, $data) {
            $season = Season::query()->lockForUpdate()->findOrFail($season->id);
            $newStatus = $data['status'] ?? $season->status;

            if ($newStatus === 'published' && ! $season->published_at) {
                $data['published_at'] = now();
            }

            if (in_array($newStatus, ['finished', 'cancelled'], true) && ! $season->closed_at) {
                $data['closed_at'] = now();
                $data['closed_season_id'] = $season->id;
            }

            return $this->seasonRepository->update($season, $data);
        });

        $this->redisHelper->deleteByPartialKey('site:season:'.$updatedSeason->id.':getVenue');

        return $updatedSeason;
    }

    public function delete(Season $season): void
    {
        DB::transaction(function () use ($season) {
            $season = Season::query()->lockForUpdate()->findOrFail($season->id);

            if (! $season->closed_at) {
                $season->update([
                    'status' => 'cancelled',
                    'closed_at' => now(),
                    'closed_season_id' => $season->id,
                ]);
            }

            $this->seasonRepository->delete($season);
        });

        $this->redisHelper->deleteByPartialKey('site:season:'.$season->id.':getVenue');
    }

    private function throwFriendlyUniqueException(QueryException $exception): void
    {
        if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'venue_id' => ['open_season_already_exists_for_show_and_venue'],
        ]);
    }
}
