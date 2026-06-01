<?php

namespace App\Helpers;

use Illuminate\Cache\Lock;
use Illuminate\Http\Request;
use Illuminate\Cache\RedisLock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;


class LockHelper
{

    private $redisIsUp = false;


    public function __construct()
    {
        try {
            Redis::connection('locks');
            $this->redisIsUp = true;
        } catch (\Exception $exception) {
            report($exception);
            $this->redisIsUp = false;
        }
    }


    public function getLock(string $lockName, int $seconds): ?Lock
    {
        if (!$this->redisIsUp()) {
            return null;
        }
        return Cache::store('redis-locks')->lock($lockName, $seconds);
    }


    public function redisIsUp(): bool
    {
        return $this->redisIsUp;
    }


    public function lockRequest(Request $req, int $seconds = 1): ?Lock
    {
        if (!method_exists($req, 'getRequestName')) {
            return null;
        }
        $lockName = $req->getRequestName();
        return $this->getLock($lockName, $seconds);
    }


    public function getLockByRequest(Request $req, int $seconds = 1): bool
    {
        $lock = $this->lockRequest($req, $seconds);
        return $lock ? $lock->get() : true;
    }


    public function getLockByName(string $lockName, int $seconds = 1): bool
    {
        $lock = $this->getLock($lockName, $seconds);
        return $lock ? $lock->get() : true;
    }


    public function releaseLockByName(string $lockName): bool
    {
        if (!$this->redisIsUp()) {
            return true;
        }
        $keys = Redis::connection('locks')->keys("*{$lockName}");
        $key = $keys[0] ?? null;
        if (!$key) {
            return false;
        }
        $redisPrefix = config('database.redis.options.prefix');
        $key = str_replace($redisPrefix, '', $key);
        $ok = Redis::connection('locks')->del($key);
        return $ok;
    }


    public function delayReleaseLockByName(string $lockName, int $delaySeconds): bool
    {
        if (!$this->redisIsUp()) {
            return true;
        }
        $keys = Redis::connection('locks')->keys("*{$lockName}");
        $key = $keys[0] ?? null;
        if (!$key) {
            return false;
        }
        $redisPrefix = config('database.redis.options.prefix');
        $key = str_replace($redisPrefix, '', $key);
        // Establecer el tiempo de expiración del lock
        $ok = Redis::connection('locks')->expire($key, $delaySeconds);
        return (bool) $ok;
    }

}
