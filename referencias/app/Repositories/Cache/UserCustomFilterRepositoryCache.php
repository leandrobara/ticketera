<?php

namespace App\Repositories\Cache;

use App\Models\User;
use App\Models\Client;
use App\Helpers\RedisHelper;
use App\Models\UserCustomFilter;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use App\DTO\UserCustomFilter\UserCustomFilterDTO;


class UserCustomFilterRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findAllByClient(Client $client): Collection
    {
        $key = $this->getMethodRedisKey('all');
        $filters = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $filters;
    }


    public function findAllByUserAndClient(User $user, Client $client): Collection
    {
        $key = $this->getMethodRedisKey('user' . $user->id);
        $filters = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $filters;
    }


    public function create(/*UserCustomFilterDTO */$dto): UserCustomFilter
    {
        $model = $this->repository->create($dto);
        $this->clearCacheForClient($model->client_id);
        return $model;
    }


    public function update(/*UserCustomFilter */$userCustomFilter, /*UserCustomFilterDTO */$dto): UserCustomFilter
    {
        $model = $this->repository->update($userCustomFilter, $dto);
        $this->clearCacheForClient($model->client_id);
        return $model;
    }


    public function delete(/*UserCustomFilter */$userCustomFilter): UserCustomFilter
    {
        $model = $this->repository->delete($userCustomFilter);
        $this->clearCacheForClient($model->client_id);
        return $model;
    }

}
