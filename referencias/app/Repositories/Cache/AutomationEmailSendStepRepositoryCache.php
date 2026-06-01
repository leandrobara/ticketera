<?php

namespace App\Repositories\Cache;

use Exception;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\AutomationEmailSend;
use App\Models\AutomationEmailSendStep;
use App\DTO\Automations\AutomationEmailSendStepDTO;


class AutomationEmailSendStepRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findByAutomationEmailSend(AutomationEmailSend $automationEmailSend): Collection
    {
        $key = $this->getMethodRedisKey((string) $automationEmailSend->id);
        $steps = $this->findOrStoreFromCache($automationEmailSend->client_id, $key, __FUNCTION__, func_get_args());
        return $steps;
    }


    public function create(/*AutomationEmailSendStepDTO */$dto): AutomationEmailSendStep
    {
        $step = $this->repository->create($dto);
        $this->clearCacheForClient($step->client_id);
        return $step;
    }
    

    public function update(
        /*AutomationEmailSendStep */$step,
        /*AutomationEmailSendStepDTO */$dto
    ): AutomationEmailSendStep {
        $step = $this->repository->update($step, $dto);
        $this->clearCacheForClient($step->client_id);
        return $step;
    }

    public function deleteAllByAutomationEmailSend(AutomationEmailSend $automation): bool
    {
        $response = $this->repository->deleteAllByAutomationEmailSend($automation);
        $this->clearCacheForClient($automation->client_id);
        return $response;
    }

}
