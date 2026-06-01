<?php

namespace App\Services\API;

use DateTime;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadSale;
use Illuminate\Support\Collection;
use App\Repositories\LeadSaleRepository;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\Cache\LeadSaleRepositoryCache;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\Services\API\Dispatchers\IntegrationAPIEventsDispatcherService;


class LeadSaleService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $leadSaleRepository;
    private $timelineDispatcherService;
    private $integrationAPIEventsDispatcherService;


    public function __construct(
        LeadSaleRepository | LeadSaleRepositoryCache $leadSaleRepository,
        TimelineEventsDispatcherService $timelineDispatcherService,
        IntegrationAPIEventsDispatcherService $integrationAPIEventsDispatcherService
    ) {
        $this->leadSaleRepository = $leadSaleRepository;
        $this->timelineDispatcherService = $timelineDispatcherService;
        $this->integrationAPIEventsDispatcherService = $integrationAPIEventsDispatcherService;
    }


    public function find(int $id)
    {
        return LeadSale::findOrFail($id);
    }


    public function create(Lead $lead, array $data): LeadSale
    {
        $data['lead_id'] = $lead->id;
        $data['user_id'] = $data['user_id'] ?? $this->getUser()->id;
        $data['client_id'] = $data['client_id'] ?? $this->getClient()->id;

        $leadSale = $this->leadSaleRepository->create($data);

        $this->timelineDispatcherService->leadSaleCreated($leadSale);
        $this->dispatchSendNewLeadSaleDataToWebhookJobIfEnabled($lead);
        
        return $leadSale;
    }


    public function update(LeadSale $leadSale, $data): LeadSale
    {
        $oldLeadSale = $leadSale->toArray();
        $oldLeadSaleUser = $leadSale->user;
        $updatedLeadSale = $this->leadSaleRepository->update($leadSale, $data);
        $this->timelineDispatcherService->leadSaleUpdated($oldLeadSale, $oldLeadSaleUser, $updatedLeadSale);
        return $updatedLeadSale;
    }


    public function delete(LeadSale $leadSale): LeadSale
    {
        $leadSale = $this->leadSaleRepository->delete($leadSale);
        $this->timelineDispatcherService->leadSaleDeleted($leadSale);
        return $leadSale;
    }


    public function list(array $options): Collection
    {
        $client = $options['filters']['client'] ?? $this->getClient();
        unset($options['filters']['client']);

        $opts = [
            'with' => $options['with'] ?? [],
            'withCount' => $options['withCount'] ?? [],
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];
        return $this->leadSaleRepository->list($client, $opts);
    }


    public function findByClientAndDates(Client $client, DateTime $dateStart, DateTime $dateEnd): Collection
    {
        return $this->leadSaleRepository->findByClientAndDates($client, $dateStart, $dateEnd);
    }


    public function findByClientAndTag(Client $client, Tag $tag, array $opts = [])
    {
        return $this->leadSaleRepository->findByClientAndTag($client, $tag, $opts);
    }


    public function findByClientAndLeads(Client $client, Collection $leads, array $opts = []): Collection
    {
        return $this->leadSaleRepository->findByClientAndLeads($client, $leads, $opts);
    }


    protected function getFilterCriteriasByName(array $filters): array
    {
        $criterias = [];

        $nfilters = [];
        foreach ($filters as $key => $value) {
            if (in_array($key, array_keys($criterias)) && $value !== null) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] = $value;
            }
        }
        return $nfilters;
    }


    protected function dispatchSendNewLeadSaleDataToWebhookJobIfEnabled(Lead $lead): void
    {
        $clientSettings = $lead->client->clientSettings;
        if (!$clientSettings->enable_integration_api) {
            return;
        }

        $webhookUrl = $clientSettings->lead_sale_trigger_webhook;
        $integrationAPIDispatcher = $this->integrationAPIEventsDispatcherService;
        if ($webhookUrl) {
            $integrationAPIDispatcher->dispatchSendNewLeadSaleDataToWebhookJob($lead, $webhookUrl);
        }

        $zapierWebhookUrl = $clientSettings->lead_sale_trigger_zapier_webhook;
        if ($zapierWebhookUrl) {
            $integrationAPIDispatcher->dispatchSendNewLeadSaleDataToWebhookJob($lead, $zapierWebhookUrl);
        }
        
        $makeWebhookUrl = $clientSettings->lead_sale_trigger_make_webhook;
        if ($makeWebhookUrl) {
            $integrationAPIDispatcher->dispatchSendNewLeadSaleDataToWebhookJob($lead, $makeWebhookUrl);
        }
    }

}
