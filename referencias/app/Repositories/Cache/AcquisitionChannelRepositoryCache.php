<?php

namespace App\Repositories\Cache;

use App\Models\Client;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;


class AcquisitionChannelRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        $key = $this->getMethodRedisKey(implode('-', $ids));
        $channels = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $channels;
    }


    public function findOneByClientAndName(Client $client, string $name): ?AcquisitionChannel
    {
        $key = $this->getMethodRedisKey($name);
        $channel = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $channel;
    }


    public function findAllByClient(Client $client): Collection
    {
        $key = $this->getMethodRedisKey('all');
        $channels = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $channels;
    }

}
