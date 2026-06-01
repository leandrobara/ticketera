<?php

namespace App\Repositories\Cache;

use Exception;
use App\Models\Client;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\AutomationEmailSend;
use App\DTO\Automations\AutomationEmailSendDTO;


class AutomationEmailSendRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findByClient(Client $client): Collection
    {
        $key = $this->getMethodRedisKey('all');
        $automations = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $automations;
    }


    public function findOneByClientAndTrigger(Client $client, string $trigger): ?AutomationEmailSend
    {
        $key = $this->getMethodRedisKey($trigger);
        $automation = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $automation;
    }


    public function findByClientAndTrigger(Client $client, string $trigger): Collection
    {
        $key = $this->getMethodRedisKey($trigger);
        $automations = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $automations;
    }


    public function create(/*AutomationEmailSendDTO */$dto): AutomationEmailSend
    {
        $automationEmailSend = $this->repository->create($dto);
        $this->clearCacheForClient($automationEmailSend->client_id);
        return $automationEmailSend;
    }


    public function update(
        /*AutomationEmailSend */$automationEmailSend,
        /*AutomationEmailSendDTO */$dto
    ): AutomationEmailSend {
        $automationEmailSend = $this->repository->update($automationEmailSend, $dto);
        $this->clearCacheForClient($automationEmailSend->client_id);
        return $automationEmailSend;
    }


    public function enable(AutomationEmailSend $automationEmailSend): AutomationEmailSend
    {
        $automationEmailSend = $this->repository->enable($automationEmailSend);
        $this->clearCacheForClient($automationEmailSend->client_id);
        return $automationEmailSend;
    }


    public function disable(AutomationEmailSend $automationEmailSend): AutomationEmailSend
    {
        $automationEmailSend = $this->repository->disable($automationEmailSend);
        $this->clearCacheForClient($automationEmailSend->client_id);
        return $automationEmailSend;
    }

}
