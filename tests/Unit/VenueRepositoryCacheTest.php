<?php

namespace Tests\Unit;

use App\Helpers\RedisHelper;
use App\Models\Season;
use App\Models\Venue;
use App\Repositories\Site\VenueRepository;
use App\Repositories\Site\VenueRepositoryCache;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class VenueRepositoryCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.enable_redis_cache' => true]);
        config(['cache.stores.redis' => ['driver' => 'array']]);
        Cache::forgetDriver('redis');
    }

    public function test_it_returns_the_cached_venue_after_the_first_lookup(): void
    {
        $season = new Season;
        $season->id = 7;

        $venue = new Venue(['name' => 'Cached venue']);
        $venue->id = 3;

        $repository = Mockery::mock(VenueRepository::class);
        $repository
            ->shouldReceive('getBySeason')
            ->once()
            ->with(Mockery::on(fn (Season $candidate): bool => $candidate->id === $season->id))
            ->andReturn($venue);

        $cache = new VenueRepositoryCache($repository, new RedisHelper);

        $this->assertSame($venue, $cache->getBySeason($season));
        $this->assertEquals($venue, $cache->getBySeason($season));
    }
}
