<?php

namespace Tests\Unit;

use App\Helpers\RedisHelper;
use App\Models\Show;
use App\Repositories\Site\ShowRepository;
use App\Repositories\Site\ShowRepositoryCache;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ShowRepositoryCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.enable_redis_cache' => true]);
        config(['cache.stores.redis' => ['driver' => 'array']]);
        Cache::forgetDriver('redis');
    }

    public function test_it_returns_the_cached_public_show_after_the_first_lookup(): void
    {
        $show = new Show(['title' => 'Cached show']);
        $show->id = 12;

        $repository = Mockery::mock(ShowRepository::class);
        $repository
            ->shouldReceive('getPublicShow')
            ->once()
            ->with(Mockery::on(fn (Show $candidate): bool => $candidate->id === $show->id))
            ->andReturn($show);

        $cache = new ShowRepositoryCache($repository, new RedisHelper);

        $this->assertSame($show, $cache->getPublicShow($show));
        $this->assertEquals($show, $cache->getPublicShow($show));
    }
}
