<?php

namespace App\Repositories\Cache;

use App\Models\Tag;
use App\Models\Client;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;


class TagRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findAllByClient(Client $client): Collection
    {
        $key = $this->getMethodRedisKey('all');
        $tags = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $tags;
    }


    public function findOneByClientAndName(Client $client, string $name): ?Tag
    {
        $key = $this->getMethodRedisKey($name);
        $tag = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $tag;
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findByClientIdAndIds($client->id, $ids);
    }


    public function findByClientIdAndIds(int $clientId, array $ids): Collection
    {
        $key = $this->getMethodRedisKey(implode('-', $ids));
        $tags = $this->findOrStoreFromCache($clientId, $key, __FUNCTION__, func_get_args());
        return $tags;
    }


    public function findWithTrashedByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findWithTrashedByClientIdAndIds($client->id, $ids);
    }


    public function findWithTrashedByClientIdAndIds(int $clientId, array $ids): Collection
    {
        $key = $this->getMethodRedisKey('with-trashed:' . implode('-', $ids));
        $tags = $this->findOrStoreFromCache($clientId, $key, __FUNCTION__, func_get_args());
        return $tags;
    }


    public function findOneWithTrashedByClientAndName(Client $client, string $name): ?Tag
    {
        $key = $this->getMethodRedisKey('with-trashed:' . $name);
        $tag = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $tag;
    }

}
