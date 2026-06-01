<?php

namespace App\Repositories\Cache;

use App\Models\User;
use App\Models\Client;
use App\Models\NPSPoll;
use Illuminate\Support\Str;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;


class NPSPollRepositoryCache extends RepositoryBaseCache implements Repository
{
    
    public function findCurrentUnscoredByUser(User $user): ?NPSPoll
    {
        $key = $this->getMethodRedisKey($user->id);
        $redisOpts = ['storeNull' => true];
        $NPSPoll = $this->findOrStoreFromCache($user->client_id, $key, __FUNCTION__, func_get_args(), $redisOpts);
        return $NPSPoll;
    }
    
}
