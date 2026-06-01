<?php

namespace App\Services\API\Import;

use Exception;
use Throwable;
use DateTimeZone;
use Carbon\Carbon;
use App\Models\Client;
use App\Models\Manager;
use Illuminate\Support\Str;
use App\Models\ClientSettings;
use App\Services\API\UserService;
use App\Services\API\NewsService;
use App\DTO\Import\LeadsClientDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\API\StatusService;
use App\Services\API\ClientService;
use App\Services\API\LandingService;
use App\Services\API\ManagerService;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use App\Services\API\AcquisitionChannelService;
use App\Services\API\Automations\AutomationProposalService;
use App\Services\API\Automations\AutomationEmailSendService;
use App\Services\API\WAutomations\WAutomationProposalService;
use App\Services\API\WAutomations\WAutomationSequenceService;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;


class ImportClientService
{

    public function __construct(
        public readonly string $leadsAPIEndpoint,
        public readonly string $leadsAPISecret,
        public readonly ClientService $clientService,
        public readonly UserService $userService,
        public readonly ManagerService $managerService,
        public readonly LandingService $landingService,
        public readonly StatusService $statusService,
        public readonly NewsService $newsService,
        public readonly AcquisitionChannelService $acquisitionChannelService,
        public readonly AutomationProposalService $automationProposalService,
        public readonly WAutomationProposalService $wAutomationProposalService,
        public readonly AutomationEmailSendService $automationEmailSendService,
        public readonly WAutomationSequenceService $wAutomationSequenceService,
        public readonly ClientEventsDispatcherService $clientEventsDispatcherService,
    ) {
    }


    public function getLeadsClients(?int $leadsClientId = null): Collection
    {
        $timestamp = Carbon::now()->setTimezone('America/Argentina/Buenos_Aires')->getTimestamp();
        $params = [
            'timestamp' => $timestamp,
            'only_clienty_crm' => true,
            'include_disabled' => true,
            'hash' => md5($this->leadsAPISecret . $timestamp),
        ];
        if ($leadsClientId) {
            $params['client_id'] = $leadsClientId;
        }
        $response = Http::asJson()
            ->withOptions(['verify' => false])
            ->get($this->leadsAPIEndpoint . '/clients/clients_secure.php', $params)
        ;
        $clients = collect($response->json());
        $clients = $clients->map(function ($leadClientData) {
            return LeadsClientDTO::buildFromLeadsClientData($leadClientData);
        });
        return collect($clients);
    }


    public function importLeadsClient(LeadsClientDTO $leadsClientDTO): Client
    {
        $subdomain = strtolower(Str::slug($leadsClientDTO->name, ''));
        $leadsClientId = $leadsClientDTO->id;
        $client = $this->clientService->findOneBySubdomain($subdomain);
        $client = $client ?? $this->clientService->findClientByLeadsId($leadsClientId);
        $manager = $this->managerService->createOrUpdate($leadsClientDTO->manager);
        if (!$client) {
            return $this->createNewClient($leadsClientDTO, $manager);
        }
        return $this->updateExistingClient($client, $leadsClientDTO, $manager);
    }


    protected function createNewClient(LeadsClientDTO $leadsClientDTO, Manager $manager): Client
    {
        $client = new Client();
        $clientSettings = new ClientSettings();
        $client = $this->getClientFilledByLeadsDTO($client, $leadsClientDTO);
        $clientSettings = $this->fillClientSettingsWithDTO($clientSettings, $leadsClientDTO, 'create');

        try {
            DB::beginTransaction();
                        
            $clientSettings->saveOrFail();

            $client->manager_id = $manager->id;
            $client->client_settings_id = $clientSettings->id;
            $client->saveOrFail();

            foreach (($leadsClientDTO->landings ?? []) as $leadsLandingId => $landingUrl) {
                $this->landingService->findOrCreateByClientAndUrl(
                    $client, $landingUrl, ['leads_landing_id' => $leadsLandingId]
                );
            }

            $userAttrs = ['email' => $leadsClientDTO->emails[0]];
            $user = $this->userService->createNewClientDefault($client, $userAttrs);

            $this->statusService->createNewClientDefaults($client);
            $this->newsService->createNewClientDefaultNotifications($client);
            $this->acquisitionChannelService->createNewClientDefaults($client);
            
            $this->automationEmailSendService->createNewClientDefaultAfterSale($client);
            $this->automationEmailSendService->createNewClientDefaultAfterSentProposal($client);
            $this->automationProposalService->createNewClientDefault($client, $user);
            
            $this->wAutomationProposalService->createNewClientDefault($client, $user);
            $this->wAutomationSequenceService->createNewClientDefaultAfterSale($client);
            $this->wAutomationSequenceService->createNewClientDefaultAfterSentProposal($client);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $client = $client->fresh();
        $this->clientEventsDispatcherService->dispatchClearClientCacheJob($client);
        $this->clientEventsDispatcherService->dispatchCreateClientEmailTemplatesByBusinessAreaJob(
            $client, $leadsClientDTO
        );
        $this->clientEventsDispatcherService->dispatchCreateClientWhatsAppTemplatesByBusinessAreaJob(
            $client, $leadsClientDTO
        );
        $client->wasCreated = true;
        return $client;
    }


    protected function updateExistingClient(Client $oldClient, LeadsClientDTO $leadsClientDTO, Manager $manager): Client
    {
        $oldClientSettings = $oldClient->clientSettings;
        $oldClientAttrs = collect($oldClient->getAttributes())->forget(['updated_at', 'emails']);
        $oldClientSettingsAttrs = collect($oldClientSettings->getAttributes())->forget(['updated_at']);
        
        $newClient = $this->getClientFilledByLeadsDTO($oldClient, $leadsClientDTO);
        $newClientSettings = $this->fillClientSettingsWithDTO($oldClientSettings, $leadsClientDTO, 'update');
        
        $newClientAttrs = collect($newClient->getAttributes())->forget(['updated_at', 'emails']);
        $newClientSettingsAttrs = collect($newClientSettings->getAttributes())->forget(['updated_at']);
        
        // Fix para comparar emails (al llamara a getAttributes, viene el array como string,
        // y difiere el espacio entre comas, lo que hace que siempre figure como distinto el campo)
        $clientEmailsHasChanged = $oldClient->emails != $newClient->emails;

        // Este método diff() de los Collection es PESIMO, no detecta mil casos de diferencia.
        // $clientSettingsHasChanged = $newClientSettingsAttrs->diff($oldClientSettingsAttrs)->isNotEmpty();
        $clientSettingsHasChanged = $this->collectionDiff(
            $oldClientSettingsAttrs, $newClientSettingsAttrs
        )->isNotEmpty();

        // No contemplo comparación de subdominio (por si fue cambiado a mano)
        unset($oldClientAttrs['subdomain'], $newClientAttrs['subdomain']);
        $clientHasChanged = $this->collectionDiff($oldClientAttrs, $newClientAttrs)->isNotEmpty();

        if ($clientHasChanged || $clientSettingsHasChanged) {
            unset($newClient->subdomain); // No actualizo subdominio

            $newClient->saveOrFail();
            $newClientSettings->saveOrFail();
            $this->clientEventsDispatcherService->dispatchClearClientCacheJob($newClient);
            $newClient = $newClient->fresh();
            $newClient->wasUpdated = true;
        }
        return $newClient;
    }


    protected function getClientFilledByLeadsDTO(Client $oldClient, LeadsClientDTO $leadsClientDTO): Client
    {
        $client = clone $oldClient;
        $fillData = [
            'version' => 'pro',
            'name' => $leadsClientDTO->name,
            'leads_client_id' => $leadsClientDTO->id,
            'country_code' => $leadsClientDTO->countryCode,
            'contract_type' => $leadsClientDTO->contractType ?? 'clienty',
            'email_from_name' => $leadsClientDTO->email_from_name ?? null,
            'subdomain' => strtolower(Str::slug($leadsClientDTO->name, '')),
            'business_area' => $leadsClientDTO?->businessArea['name'] ?? null,
            'enabled' => $leadsClientDTO->enabled && $leadsClientDTO->CRMEnabled,
            'emails' => $leadsClientDTO->emails ? $leadsClientDTO->emails : null,
            'google_ads_id' => $leadsClientDTO->googleAdsId ? $leadsClientDTO->googleAdsId : null,
        ];
        // Solo lo actualizo si al cliente se le cambió el pais, o es un nuevo cliente.
        if (!$client->id || ($client->country_code != $leadsClientDTO->countryCode)) {
            $fillData['timezone'] = getTimezoneStringByCountryCode($leadsClientDTO->countryCode);
        }
        $client->fill($fillData);
        return $client;
    }



    // @param $operation string ('create' | 'update')
    protected function fillClientSettingsWithDTO(
        ClientSettings $clientSettings,
        LeadsClientDTO $leadsClientDTO,
        string $operation
    ): ClientSettings {
        if ($operation == 'create') {
            $clientSettings->enable_wapi = true;
            $clientSettings->acquired_landings = 3;
            $clientSettings->enable_wapi_conversation_chat = true;
            $isGodixitalClient = $leadsClientDTO->contractType == 'godixital';
            // Si es de agencia, entra con 10 clientes, si es de Clienty, entra con 3 por default.
            $clientSettings->acquired_users = $isGodixitalClient ? 10 : 3;
        }
        return $clientSettings;
    }


    protected function collectionDiff(Collection $collection1, Collection $collection2): Collection
    {
        $arr1 = $collection1->toArray();
        $arr2 = $collection2->toArray();
        $diffCollection = new Collection();
        
        foreach ($arr1 as $key => $val1) {
            $val2 = array_key_exists($key, $arr2) ? $arr2[$key] : '___undefined___';
            if ($val1 != $val2) {
                $diffCollection->push(['value1' => $val1, 'value2' => $val2]);
            }
        }
        return $diffCollection;
    }

}
