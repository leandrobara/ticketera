<?php

namespace App\Repositories\Cache;

use App\Helpers\RedisHelper;
use Closure;

abstract class RepositoryCache
{
    public function __construct(
        protected readonly RedisHelper $cache,
    ) {
        //
    }

    protected function remember(string $key, int $seconds, Closure $callback): mixed
    {
        return $this->cache->remember($key, $seconds, $callback);
    }
}
