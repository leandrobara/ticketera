<?php

namespace App\Helpers;

use Closure;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;

class RedisHelper
{
    public function remember(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        if (! config('app.enable_redis_cache')) {
            return $callback();
        }

        return Cache::store('redis')->remember(
            $key,
            now()->addSeconds($ttlSeconds),
            $callback,
        );
    }

    public function forget(string $key): bool
    {
        if (! config('app.enable_redis_cache')) {
            return false;
        }

        return Cache::store('redis')->forget($key);
    }

    public function deleteByPartialKey(string $partialKey): int
    {
        if (! config('app.enable_redis_cache')) {
            return 0;
        }

        $store = Cache::store('redis')->getStore();

        if (! $store instanceof RedisStore) {
            return 0;
        }

        $connection = $store->connection();
        $client = $connection->client();

        if (! method_exists($client, 'rawCommand') || ! method_exists($client, 'getOption')) {
            return 0;
        }

        $cursor = '0';
        $deleted = 0;
        $connectionPrefix = (string) $client->getOption(\Redis::OPT_PREFIX);
        $pattern = $connectionPrefix.$store->getPrefix().'*'.$partialKey.'*';

        do {
            $result = $client->rawCommand('SCAN', $cursor, 'MATCH', $pattern, 'COUNT', '100');

            if (! is_array($result) || count($result) !== 2) {
                break;
            }

            [$cursor, $keys] = $result;

            if ($keys !== []) {
                $deleted += (int) $client->rawCommand('DEL', ...$keys);
            }
        } while ((string) $cursor !== '0');

        return $deleted;
    }
}
