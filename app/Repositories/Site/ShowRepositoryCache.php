<?php

namespace App\Repositories\Site;

use App\Models\Show;
use App\Helpers\RedisHelper;
use App\Repositories\Cache\RepositoryCache;

class ShowRepositoryCache extends RepositoryCache implements SiteShowRepositoryInterface
{
    public function __construct(
        private readonly SiteShowRepositoryInterface $repository,
        RedisHelper $cache,
    ) {
        parent::__construct($cache);
    }

    public function getPublicShow(Show $show): Show
    {
        return $this->remember(
            'site:show:'.$show->id.':getPublicShow',
            (int) config('cache.show_ttl'),
            fn (): Show => $this->repository->getPublicShow($show),
        );
    }
}
