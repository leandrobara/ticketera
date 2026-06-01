<?php

namespace App\Repositories\Cache;

use Exception;
use App\Models\Client;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\WAutomationSequence;


class WAutomationSequenceRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findByClient(Client $client): Collection
    {
        $key = $this->getMethodRedisKey('all');
        $wAutomations = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $wAutomations;
    }


    public function findOneByClientAndTrigger(Client $client, string $trigger): ?WAutomationSequence
    {
        $key = $this->getMethodRedisKey($trigger);
        $wAutomation = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $wAutomation;
    }


    public function findByClientAndTrigger(Client $client, string $trigger): Collection
    {
        $key = $this->getMethodRedisKey($trigger);
        $wAutomations = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $wAutomations;
    }


    public function enable(WAutomationSequence $wAutomationSequence): WAutomationSequence
    {
        $wAutomationSequence = $this->repository->enable($wAutomationSequence);
        $this->clearCacheForClient($wAutomationSequence->client_id);
        return $wAutomationSequence;
    }


    public function disable(WAutomationSequence $wAutomationSequence): WAutomationSequence
    {
        $wAutomationSequence = $this->repository->disable($wAutomationSequence);
        $this->clearCacheForClient($wAutomationSequence->client_id);
        return $wAutomationSequence;
    }

}
