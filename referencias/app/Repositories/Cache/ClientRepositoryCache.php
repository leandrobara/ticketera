<?php

namespace App\Repositories\Cache;

use App\Models\User;
use App\Models\Client;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use Illuminate\Database\Eloquent\Model;


class ClientRepositoryCache extends RepositoryBaseCache
{

    public function findOneBySubdomain(string $subdomain): ?Client
    {
        $key = $this->getMethodRedisKey($subdomain);
        $universalClientIdCacheKey = config('app.universal_client_id_cache_key');
        $client = $this->findOrStoreFromCache($universalClientIdCacheKey, $key, __FUNCTION__, func_get_args());
        return $client;
    }


    public function update(/* Model*/$client, /*array */$data): Client
    {
        $client = $this->repository->update($client, $data);
        $this->clearCacheForClient($client->id);
        $this->clearSubdomainClientModelCache($client);
        return $client;
    }


    public function clearSubdomainClientModelCache(Client $client): bool
    {
        // Borro el objeto client que se guarda para traer segun subdomain (no tiene clientId en ese instante).
        $universalClientIdCacheKey = config('app.universal_client_id_cache_key');
        $key = "Client:findOneBySubdomain:{$client->subdomain}";
        $ok = $this->redisHelper->setClientId($universalClientIdCacheKey)->delete($key);
        return $ok;
    }

}
