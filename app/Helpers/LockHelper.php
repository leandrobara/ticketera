<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class LockHelper
{
    public function getLockByName(string $lockName, int $seconds = 10): bool
    {
        return Cache::store('redis')->lock($lockName, $seconds)->get();
    }

    public function releaseLockByName(string $lockName): void
    {
        Cache::store('redis')->lock($lockName)->forceRelease();
    }
}
