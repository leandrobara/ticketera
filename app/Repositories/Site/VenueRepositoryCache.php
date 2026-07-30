<?php

namespace App\Repositories\Site;

use App\Helpers\RedisHelper;
use App\Models\Season;
use App\Models\Venue;
use App\Repositories\Cache\RepositoryCache;

class VenueRepositoryCache extends RepositoryCache implements SiteVenueRepositoryInterface
{
    public function __construct(
        private readonly SiteVenueRepositoryInterface $repository,
        RedisHelper $cache,
    ) {
        parent::__construct($cache);
    }

    public function getBySeason(Season $season): Venue
    {
        return $this->remember(
            'site:season:'.$season->id.':getVenue',
            (int) config('cache.venue_ttl'),
            fn (): Venue => $this->repository->getBySeason($season),
        );
    }
}
