<?php

namespace App\Repositories\Cache;

use App\Models\Task;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use Illuminate\Database\Eloquent\Model;


class TaskRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findByFiltersAndClient(Client $client, array $opts = []): Collection
    {
        $key = $this->getMethodRedisKey(md5(serialize($opts)));
        $redisOpts = ['ttlSeconds' => 3600, 'storeEmpty' => true];
        $tasks = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args(), $redisOpts);
        return $tasks;
    }

}
