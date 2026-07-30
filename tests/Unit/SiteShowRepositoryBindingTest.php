<?php

namespace Tests\Unit;

use App\Repositories\Site\ShowRepository;
use App\Repositories\Site\ShowRepositoryCache;
use App\Repositories\Site\SiteShowRepositoryInterface;
use App\Repositories\Site\SiteVenueRepositoryInterface;
use App\Repositories\Site\VenueRepository;
use App\Repositories\Site\VenueRepositoryCache;
use Tests\TestCase;

class SiteShowRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_database_repository_when_redis_cache_is_disabled(): void
    {
        config(['app.enable_redis_cache' => false]);

        $repository = $this->app->make(SiteShowRepositoryInterface::class);

        $this->assertInstanceOf(ShowRepository::class, $repository);
    }

    public function test_it_resolves_the_cached_repository_when_redis_cache_is_enabled(): void
    {
        config(['app.enable_redis_cache' => true]);

        $repository = $this->app->make(SiteShowRepositoryInterface::class);

        $this->assertInstanceOf(ShowRepositoryCache::class, $repository);
    }

    public function test_it_resolves_the_database_venue_repository_when_redis_cache_is_disabled(): void
    {
        config(['app.enable_redis_cache' => false]);

        $repository = $this->app->make(SiteVenueRepositoryInterface::class);

        $this->assertInstanceOf(VenueRepository::class, $repository);
    }

    public function test_it_resolves_the_cached_venue_repository_when_redis_cache_is_enabled(): void
    {
        config(['app.enable_redis_cache' => true]);

        $repository = $this->app->make(SiteVenueRepositoryInterface::class);

        $this->assertInstanceOf(VenueRepositoryCache::class, $repository);
    }
}
