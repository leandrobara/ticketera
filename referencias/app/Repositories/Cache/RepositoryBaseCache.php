<?php

namespace App\Repositories\Cache;

use App\Models\Client;
use Illuminate\Support\Str;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;


abstract class RepositoryBaseCache
{

    protected $repository;
    protected $redisHelper;
    protected $repoRedisKey;


    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
        $this->redisHelper = resolve(RedisHelper::class);
    }


    public function __call(string $methodName, array $arguments)
    {
        return call_user_func_array([$this->repository, $methodName], $arguments);
    }


    public function findOrStoreFromCache(
        int $clientId,
        string $key,
        string $repoMethodName,
        array $repoMethodArgs,
        array $opts = []
    ) {
        if ($this->redisHelper->redisIsDown()) {
            return call_user_func_array([$this->repository, $repoMethodName], $repoMethodArgs);
        }

        $storeNull = $opts['storeNull'] ?? false;
        $storeEmpty = $opts['storeEmpty'] ?? false;
        $ttlSeconds = $opts['ttlSeconds'] ?? 86400;
        $storeOpts = ['ttlSeconds' => $ttlSeconds];

        $cacheElement = $this->findFromCache($clientId, $key);
        $cacheElementIsNull = $cacheElement === null;
        if (!$cacheElementIsNull) {
            return $cacheElement;
        }

        $element = call_user_func_array([$this->repository, $repoMethodName], $repoMethodArgs);

        $elementIsNull = $element === null;
        $elementIsArray = is_array($element);
        $elementIsCollection = is_a($element, Collection::class);
        $elementIsEmpty = ($elementIsCollection && $element->isEmpty()) || ($elementIsArray && !$element);
        
        if ($elementIsCollection && (!$elementIsEmpty || $storeEmpty)) {
            $this->storeInCache($clientId, $key, $element, $storeOpts);
            return $element;
        }

        if ($elementIsArray && (!$elementIsEmpty || $storeEmpty)) {
            $this->storeInCache($clientId, $key, $element, $storeOpts);
            return $element;
        }

        if (!$elementIsNull || $storeNull) {
            $this->storeInCache($clientId, $key, $element, $storeOpts);
            return $element;
        }

        return $element;
    }


    public function findFromCache(int $clientId, string $key, array $opts = [])
    {
        if ($this->redisHelper->redisIsDown()) {
            return null;
        }
        return $this->redisHelper->setClientId($clientId)->get($key);
    }


    public function storeInCache(int $clientId, string $key, $element, array $opts = [])
    {
        if ($this->redisHelper->redisIsDown()) {
            return false;
        }
        return $this->redisHelper->setClientId($clientId)->store($key, $element, $opts);
    }


    public function exists(int $clientId, string $key, array $opts = []): bool
    {
        if ($this->redisHelper->redisIsDown()) {
            return false;
        }
        return $this->redisHelper->setClientId($clientId)->exists($key);
    }


    public function clearCacheForClient(int $clientId): bool
    {
        if ($this->redisHelper->redisIsDown()) {
            return false;
        }
        return $this->redisHelper->deleteForClientByPartialKey($this->getRepoModelNameRedisKey(), $clientId);
    }


    public function clearCacheForAllClients(): bool
    {
        if ($this->redisHelper->redisIsDown()) {
            return false;
        }
        return $this->redisHelper->deleteForAllClientsByPartialKey($this->getRepoModelNameRedisKey());
    }


    public function getRepoModelNameRedisKey(): string
    {
        $repoName = get_class($this);
        $repoModelNameRedisKey = Str::afterLast($repoName, '\\');
        $repoModelNameRedisKey = Str::before($repoModelNameRedisKey, 'Repo');
        return $repoModelNameRedisKey;
    }


    public function getMethodRedisKey(string $key)
    {
        $repoModelNameRedisKey = $this->getRepoModelNameRedisKey();
        $methodWhoCalled = debug_backtrace()[1]['function'];
        $redisKey = $this->getRepoModelNameRedisKey() . ':' . $methodWhoCalled . ':' . $key;
        return $redisKey;
    }


    public function create(/*array */$data): Model
    {
        $model = $this->repository->create($data);
        $this->clearCacheForClient($model->client_id);
        return $model;
    }
    

    public function update(/*Model */$model, /*array */$data): Model
    {
        $model = $this->repository->update($model, $data);
        $this->clearCacheForClient($model->client_id);
        return $model;
    }
    

    public function delete(/*Model */$model): Model
    {
        $model = $this->repository->delete($model);
        $this->clearCacheForClient($model->client_id);
        return $model;
    }

}
