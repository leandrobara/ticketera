<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;


class QueuedJobsCounter
{

    protected int $ttlSeconds;
    protected string $lockName;
    protected bool $redisIsUp = false;


    public function __construct(int $ttlSeconds)
    {
        $this->ttlSeconds = $ttlSeconds;

        try {
            Redis::connection('cache');
            $this->redisIsUp = true;
        } catch (Exception $exception) {
            report($exception);
            $this->redisIsUp = false;
        }
    }


    public function createOrGet(string $lockName): int
    {
        if (!$this->redisIsUp()) {
            return 0;
        }

        $key = $this->getCounterKey($lockName);

        if (!Cache::store('redis')->has($key)) {
            Cache::store('redis')->put($key, 0, $this->ttlSeconds);
        }

        $counter = (int) Cache::store('redis')->get($key);
        return $this->validateCounterValue($counter);
    }


    public function increment(string $lockName, int $qty = 1): int
    {
        if (!$this->redisIsUp()) {
            return 0;
        }

        $counter = $this->createOrGet($lockName);
        $newCounter = $counter + $qty;

        $key = $this->getCounterKey($lockName);
        Cache::store('redis')->put($key, $newCounter, $this->ttlSeconds);

        return $newCounter;
    }


    protected function getCounterKey(string $lockName): string
    {
        return "{$lockName}:counter";
    }


    protected function validateCounterValue(int $counter): int
    {
        return ($counter !== null && $counter >= 0) ? $counter : 0;
    }


    public function redisIsUp(): bool
    {
        return $this->redisIsUp;
    }

}
