<?php

namespace App\Jobs\ClientEvents;

use Throwable;
use App\Models\Client;
use App\Helpers\RedisHelper;
use Illuminate\Bus\Queueable;
use App\Services\API\ClientService;
use Illuminate\Queue\SerializesModels;
use App\Repositories\ClientRepository;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\ClientEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Repositories\Cache\ClientRepositoryCache;
use App\Overrides\Dispatchers\CustomDispatchable;


class ClearClientCacheJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    
    public $clientId;
    

    public function __construct(int $clientId)
    {
        $this->clientId = $clientId;
    }


    public function handle()
    {
        $redisHelper = resolve(RedisHelper::class, ['clientId' => $this->clientId]);
        if ($redisHelper->redisIsDown()) {
            return true;
        }
        
        $redisHelper->deleteAllClientCache($this->clientId);
        
        $client = resolve(ClientService::class)->findOneById($this->clientId);
        $clientRepoCache = new ClientRepositoryCache(new ClientRepository());
        $clientRepoCache->clearSubdomainClientModelCache($client);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
