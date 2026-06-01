<?php

namespace App\Repositories\Cache;

use App\Models\User;
use App\Models\Client;
use App\Helpers\RedisHelper;
use App\Models\WhatsAppTemplate;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;


class WhatsAppTemplateRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findById(int $id): ?WhatsAppTemplate
    {
        $key = $this->getMethodRedisKey((string) $id);
        $universalClientIdCacheKey = config('app.wap_meta_api_conn_universal_client_id_mock');
        return $this->findOrStoreFromCache($universalClientIdCacheKey, $key, __FUNCTION__, func_get_args());
    }


    public function findAllByClient(Client $client): Collection
    {
        $key = $this->getMethodRedisKey('all');
        $tpls = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $tpls;
    }

}
