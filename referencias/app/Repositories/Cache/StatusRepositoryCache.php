<?php

namespace App\Repositories\Cache;

use App\Models\Status;
use App\Models\Client;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;


class StatusRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findAllByClient(Client $client): Collection
    {
        $key = $this->getMethodRedisKey('all');
        $statusList = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $statusList;
    }


    public function findOneByStatusIdAndClientId(int $statusId, int $clientId): ?Status
    {
        $key = $this->getMethodRedisKey("statusId-$statusId");
        $status = $this->findOrStoreFromCache($clientId, $key, __FUNCTION__, func_get_args());
        return $status;
    }


    public function findByClientAndSaleProbability(Client $client, int $saleProbability): Collection
    {
        $key = $this->getMethodRedisKey("$saleProbability");
        $statusList = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $statusList;
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findByClientIdAndIds($client->id, $ids);
    }


    public function findByClientIdAndIds(int $clientId, array $ids): Collection
    {
        $key = $this->getMethodRedisKey(implode('-', $ids));
        $statusList = $this->findOrStoreFromCache($clientId, $key, __FUNCTION__, func_get_args());
        return $statusList;
    }


    public function findWithTrashedByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findWithTrashedByClientIdAndIds($client->id, $ids);
    }


    public function findWithTrashedByClientIdAndIds(int $clientId, array $ids): Collection
    {
        $key = $this->getMethodRedisKey(implode('-', $ids));
        $statusList = $this->findOrStoreFromCache($clientId, $key, __FUNCTION__, func_get_args());
        return $statusList;
    }


    public function findOneWithTrashedByClientAndName(Client $client, string $name): ?Status
    {
        $key = $this->getMethodRedisKey($name);
        $status = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $status;
    }


    public function findOneByClientAndName(Client $client, string $name): ?Status
    {
        $key = $this->getMethodRedisKey($name);
        $status = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $status;
    }

}
