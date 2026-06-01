<?php

namespace App\Repositories\Cache;

use App\Models\Client;
use App\Models\Landing;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;


class LandingRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findAllByClient(Client $client): Collection
    {
        $key = $this->getMethodRedisKey('all');
        $landings = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $landings;
    }


    public function findOneByClientAndUrl(Client $client, string $url): ?Landing
    {
        $hash = Landing::buildHash($url);
        $key = $this->getMethodRedisKey($hash);
        $landing = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $landing;
    }

}
