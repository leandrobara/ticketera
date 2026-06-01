<?php

namespace App\Repositories\Cache;

use Exception;
use App\Models\Client;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\WAutomationSequence;
use App\Models\WAutomationSequenceStep;


class WAutomationSequenceStepRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findByWAutomationSequence(WAutomationSequence $wAutomationSequence): Collection
    {
        $key = $this->getMethodRedisKey($wAutomationSequence->id);
        $wAutomations = $this->findOrStoreFromCache(
            $wAutomationSequence->client_id, $key, __FUNCTION__, func_get_args()
        );
        return $wAutomations;
    }


    public function deleteAllByWAutomationSequence(WAutomationSequence $wAutomationSequence): bool
    {
        $ok = $this->repository->deleteAllByWAutomationSequence($wAutomationSequence);
        $this->clearCacheForClient($wAutomationSequence->client_id);
        return $ok;
    }

}
