<?php

namespace App\Services\API;

use Exception;
use App\Models\Client;
use App\Models\ClientSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Repositories\ClientRepository;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\ClientSettingsRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Cache\ClientRepositoryCache;
use App\Repositories\Criteria\Filter\Client\ClientNameLikeCriteria;
use App\Repositories\Criteria\Filter\Client\ClientCustomWAPFilterCriteria;


class ClientService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $clientRepository;
    private $clientSettingsService;


    public function __construct(
        ClientRepository | ClientRepositoryCache $clientRepository,
        ClientSettingsService $clientSettingsService
    ) {
        $this->clientRepository = $clientRepository;
        $this->clientSettingsService = $clientSettingsService;
    }


    public function list(array $opts): LengthAwarePaginator
    {
        $response = $this->clientRepository->listPaginated($opts);
        return $response;
    }


    public function clientPricingListToExport(array $opts): LengthAwarePaginator
    {
        $filters = $opts['filters'] ?? [];
        if ($filters['name'] ?? null) {
            $filters['name'] = new ClientNameLikeCriteria($filters['name']);
        }
        if ($filters['customWapFilter'] ?? null) {
            $filters['customWapFilter'] = new ClientCustomWAPFilterCriteria($filters['customWapFilter']);
        }
        $opts['limit'] = 999999;
        $opts['filters'] = $filters;
        $response = $this->list($opts);
        return $response;
    }


    public function findOneBySubdomain(string $subdomain): ?Client
    {
        return $this->clientRepository->findOneBySubdomain($subdomain);
    }


    public function findClientByLeadsId(int $clientLeadsId): ?Client
    {
        return $this->clientRepository->findClientByLeadsId($clientLeadsId);
    }


    public function findAllEnabled(): Collection
    {
        return $this->clientRepository->findAllEnabled();
    }


    public function findWithEnabledWAPI(): Collection
    {
        return $this->clientRepository->findWithEnabledWAPI();
    }


    public function findWithEnabledWAPSenderJob(array $opts = []): Collection
    {
        return $this->clientRepository->findWithEnabledWAPSenderJob($opts);
    }


    public function findWithEnabledWhatsAppMetaAPI(array $opts = []): Collection
    {
        return $this->clientRepository->findWithEnabledWhatsAppMetaAPI($opts);
    }


    public function findWithEnabledWAPIOrWapSender(): Collection
    {
        return $this->clientRepository->findWithEnabledWAPIOrWapSender();
    }


    public function findWithEnabledWAPIOrWapSenderJob(): Collection
    {
        return $this->clientRepository->findWithEnabledWAPIOrWapSenderJob();
    }


    public function findWithEnabledNewLeadWhatsAppMessageAlert(): Collection
    {
        return $this->clientRepository->findWithEnabledNewLeadWhatsAppMessageAlert();
    }


    public function findWithEnabledDailyTaskWhatsAppMessageAlert(): Collection
    {
        return $this->clientRepository->findWithEnabledDailyTaskWhatsAppMessageAlert();
    }


    public function findWithEnabledTaskHourReminderWhatsAppMessageAlert(): Collection
    {
        return $this->clientRepository->findWithEnabledTaskHourReminderWhatsAppMessageAlert();
    }


    public function findWithEnabledDailyEmailForEachExpiredTask(): Collection
    {
        return $this->clientRepository->findWithEnabledDailyEmailForEachExpiredTask();
    }


    public function findWithEnabledDailyEmailForAllExpiredTasks(): Collection
    {
        return $this->clientRepository->findWithEnabledDailyEmailForAllExpiredTasks();
    }


    public function count(): int
    {
        return $this->clientRepository->count();
    }


    public function findOneById(int $id): ?Client
    {
        return $this->clientRepository->findOneById($id);
    }


    public function updateWithSettings(Client $client, array $clientData, array $clientSettingsData): Client
    {
        try {
            DB::beginTransaction();
            $updatedClient = $this->update($client, $clientData);
            $this->updateSettingsByClient($client, $clientSettingsData);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $updatedClient->fresh();
    }


    public function update(Client $client, array $data): Client
    {
        $updated = $this->clientRepository->update($client, $data);
        return $updated;
    }


    public function updateSettingsByClient(Client $client, array $data): ClientSettings
    {
        $updated = $this->clientSettingsService->updateByClient($client, $data);
        return $updated;
    }


    public function subscribeToZapierApp(string $triggerType, string $zapierHookUrl): ClientSettings
    {
        $webhookZapierTriggerFieldName = $this->getTriggerWebhookFieldName('zapier', $triggerType);
        return $this->updateSettingsByClient($this->getClient(), [$webhookZapierTriggerFieldName => $zapierHookUrl]);
    }


    public function subscribeToMakeApp(string $triggerType, string $makeHookUrl): ClientSettings
    {
        $webhookMakeTriggerFieldName = $this->getTriggerWebhookFieldName('make', $triggerType);
        return $this->updateSettingsByClient($this->getClient(), [$webhookMakeTriggerFieldName => $makeHookUrl]);
    }


    public function unsubscribeFromZapierApp(string $triggerType): ClientSettings
    {
        $webhookZapierTriggerFieldName = $this->getTriggerWebhookFieldName('zapier', $triggerType);
        return $this->updateSettingsByClient($this->getClient(), [$webhookZapierTriggerFieldName => null]);
    }


    public function unsubscribeFromMakeApp(string $triggerType): ClientSettings
    {
        $webhookMakeTriggerFieldName = $this->getTriggerWebhookFieldName('make', $triggerType);
        return $this->updateSettingsByClient($this->getClient(), [$webhookMakeTriggerFieldName => null]);
    }


    protected function getTriggerWebhookFieldName(string $appName, string $triggerType): string
    {
        $webhookTriggerFieldNames = [
            'zapier' => [
                'newSale' => 'lead_sale_trigger_zapier_webhook',
                'newLead' => 'lead_create_trigger_zapier_webhook',
                'newTask' => 'task_create_trigger_zapier_webhook',
                'statusChange' => 'lead_status_change_trigger_zapier_webhook',
            ],
            'make' => [
                'newSale' => 'lead_sale_trigger_make_webhook',
                'newLead' => 'lead_create_trigger_make_webhook',
                'newTask' => 'task_create_trigger_make_webhook',
                'statusChange' => 'lead_status_change_trigger_make_webhook',
            ],
        ];

        $fieldName = $webhookTriggerFieldNames[$appName][$triggerType] ?? null;
        if (!$fieldName) {
            throw new Exception(strtolower($appName) . '_trigger_webhook_does_not_exist');
        }
        return $fieldName;
    }

}
