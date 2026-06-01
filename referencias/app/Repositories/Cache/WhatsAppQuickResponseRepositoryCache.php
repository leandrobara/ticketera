<?php

namespace App\Repositories\Cache;

use App\Models\Client;
use App\Models\WhatsAppQuickResponse;
use App\Repositories\Repository;
use Illuminate\Support\Collection;


class WhatsAppQuickResponseRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findById(int $id): ?WhatsAppQuickResponse
    {
        $key = $this->getMethodRedisKey((string) $id);
        $universalClientIdCacheKey = config('app.wap_meta_api_conn_universal_client_id_mock');
        return $this->findOrStoreFromCache($universalClientIdCacheKey, $key, __FUNCTION__, func_get_args());
    }


    public function findAllByClient(Client $client): Collection
    {
        $key = $this->getMethodRedisKey('all');
        return $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
    }

}
