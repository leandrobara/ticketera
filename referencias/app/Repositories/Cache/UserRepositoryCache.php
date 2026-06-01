<?php

namespace App\Repositories\Cache;

use App\Models\User;
use App\Models\Client;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;


class UserRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findAllByClient(Client $client): Collection
    {
        $key = $this->getMethodRedisKey('all');
        $users = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $users;
    }


    public function findByEmailOrUsername(Client $client, string $emailOrUsername): ?User
    {
        $key = $this->getMethodRedisKey($emailOrUsername);
        $user = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $user;
    }


    public function findOneByClientAndAPIToken(Client $client, string $apiToken): ?User
    {
        $key = $this->getMethodRedisKey($apiToken);
        $user = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $user;
    }


    public function findOneByUserIdAndClientId(int $userId, int $clientId): ?User
    {
        $key = $this->getMethodRedisKey("$userId-$clientId");
        $user = $this->findOrStoreFromCache($clientId, $key, __FUNCTION__, func_get_args());
        return $user;
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        $key = $this->getMethodRedisKey(implode('-', $ids));
        $users = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $users;
    }


    public function createNewClientDefault(Client $client, array $attrs): User
    {
        $model = $this->repository->createNewClientDefault($client, $attrs);
        $this->clearCacheForClient($client->id);
        return $model;
    }

}
