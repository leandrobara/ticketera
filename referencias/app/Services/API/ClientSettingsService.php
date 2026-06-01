<?php

namespace App\Services\API;

use App\Models\Client;
use App\Models\ClientSettings;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\ClientSettingsRepository;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;


class ClientSettingsService
{

    use GetClientFromRequest;


    public function __construct(
        protected readonly ClientSettingsRepository $clientSettingsRepository,
        protected readonly ClientEventsDispatcherService $clientEventsDispatcherService,
    ) {
    }


    public function getClientSettings()
    {
        return $this->getClient()->clientSettings;
    }


    public function update(ClientSettings $clientSettings, array $data): ClientSettings
    {
        $client = $clientSettings->client;
        $clientSettings = $this->clientSettingsRepository->update($clientSettings, $data);
        
        $clientSettings->clearRelationModelCache($client->id);
        $this->clientEventsDispatcherService->dispatchClearClientCacheJob($client);
        
        return $clientSettings;
    }


    public function updateByClient(Client $client, array $data): ClientSettings
    {
        return $this->update($client->ClientSettings, $data);
        
    }

}
