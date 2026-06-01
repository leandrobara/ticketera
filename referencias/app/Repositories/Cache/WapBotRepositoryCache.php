<?php

namespace App\Repositories\Cache;

use App\Models\WapBot;
use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Model;


class WapBotRepositoryCache extends RepositoryBaseCache implements Repository
{

    /**
     * Búsqueda global (sin client_id). Usa app.wap_bot_universal_client_id_mock como clientId.
     */
    public function find(int $wapBotId, array $opts = []): ?WapBot
    {
        $redisOpts = ['storeNull' => true];
        $key = $this->getMethodRedisKey($wapBotId);
        $universalClientIdCacheKey = config('app.wap_bot_universal_client_id_mock');
        return $this->findOrStoreFromCache(
            $universalClientIdCacheKey, $key, __FUNCTION__, func_get_args(), $redisOpts
        );
    }

    
    public function findActive(int $clientId, string $metaPhoneNumberId): ?WapBot
    {
        $key = $this->getMethodRedisKey($metaPhoneNumberId);
        return $this->findOrStoreFromCache($clientId, $key, __FUNCTION__, func_get_args());
    }


    /**
     * Búsqueda global (sin client_id). Usa app.wap_bot_universal_client_id_mock como clientId.
     */
    public function findActiveByMetaPhoneNumberId(string $metaPhoneNumberId): ?WapBot
    {
        $redisOpts = ['storeNull' => true];
        $key = $this->getMethodRedisKey($metaPhoneNumberId);
        $universalClientIdCacheKey = config('app.wap_bot_universal_client_id_mock');
        return $this->findOrStoreFromCache(
            $universalClientIdCacheKey, $key, __FUNCTION__, func_get_args(), $redisOpts
        );
    }


    /**
    * @Override Sobrescrito para también limpiar el caché global (app.wap_bot_universal_client_id_mock)
    */
    public function create(/*array */$data): Model
    {
        $model = $this->repository->create($data);
        $this->clearCacheForClient($model->client_id);
        $this->clearCacheForClient(config('app.wap_bot_universal_client_id_mock'));
        return $model;
    }

    
    /**
    * @Override Sobrescrito para también limpiar el caché global (app.wap_bot_universal_client_id_mock)
    */
    public function update(/*Model */$wapBot, /*array */$data): Model
    {
        $updatedWapBot = $this->repository->update($wapBot, $data);
        $this->clearCacheForClient($wapBot->client_id);
        $this->clearCacheForClient(config('app.wap_bot_universal_client_id_mock'));
        return $updatedWapBot;
    }

    
    /**
    * @Override Sobrescrito para también limpiar el caché global (app.wap_bot_universal_client_id_mock)
    */
    public function delete(/*Model */$wapBot): Model
    {
        $deletedWapBot = $this->repository->delete($wapBot);
        $this->clearCacheForClient($wapBot->client_id);
        $this->clearCacheForClient(config('app.wap_bot_universal_client_id_mock'));
        return $deletedWapBot;
    }

}

