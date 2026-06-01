<?php

namespace App\Services\API\Views;

use DateTime;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadSale;
use App\Models\TagCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Repositories\LeadRepository;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\Leads\SortByCreated;
use App\Repositories\Criteria\Filter\Leads\TagORCriteria;
use App\Repositories\Criteria\Filter\Leads\TagANDCriteria;
use App\Repositories\Criteria\Filter\Leads\LeadIdCriteria;
use App\Repositories\Criteria\Filter\Leads\LandingCriteria;
use App\Repositories\Criteria\Filter\Leads\LeadUTMCriteria;
use App\Repositories\Criteria\Filter\Leads\LeadTypeCriteria;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Repositories\Criteria\Filter\Leads\LeadQualityCriteria;
use App\Repositories\Criteria\Filter\Leads\OnlyUTMLeadsCriteria;
use App\Repositories\Criteria\Filter\Leads\TagORExcludeCriteria;
use App\Repositories\Criteria\Filter\Leads\TagANDExcludeCriteria;
use App\Repositories\Criteria\Sort\Leads\SortByLastStatusChanged;
use App\Repositories\Criteria\Filter\Leads\SpecialFilterCriteria;
use App\Repositories\Criteria\Filter\Leads\TagORExclusiveCriteria;
use App\Repositories\Criteria\Filter\Leads\CreatedDateEndCriteria;
use App\Repositories\Criteria\Filter\Leads\LeadCustomFieldCriteria;
use App\Repositories\Criteria\Filter\Leads\CreatedDateStartCriteria;
use App\Repositories\Criteria\Filter\Leads\ZapierTriggerTypeCriteria;
use App\Repositories\Criteria\Filter\Leads\AcquisitionChannelCriteria;


class LeadService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        private readonly LeadRepository $leadRepository,
        private readonly ClientEventsDispatcherService $clientEventsDispatcherService,
    ) {
    }


    public function list(array $options)
    {
        $opts = [
            'page' => $options['page'] ?? 1,
            'with' => $options['with'] ?? [],
            'limit' => $options['limit'] ?? 30,
            'order' => $this->getSortCriteriasByName($options['sort'] ?? ''),
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];

        // DB::enableQueryLog();
        $response = $this->leadRepository->listPaginated($this->getClient(), $opts);
        // dd(DB::getQueryLog());
        return $response;
    }


    public function listToExport(array $opts): Collection
    {
        $userIp = $opts['userIp'] ?? null;
        $opts['limit'] = $opts['limit'] ?? 9999;
        $response = $this->list($opts);
        $leads = $response->getCollection();

        $this->clientEventsDispatcherService->dispatchSendExportedLeadsNotificationJob(
            $this->getClient(), $this->getUser(), $userIp, $opts['filters'] ?? [], $leads->count()
        );
        return $leads;
    }


    public function listForMakeApp(array $opts): LengthAwarePaginator
    {
        return $this->list($opts);
    }


    public function listForZapierApp(array $opts): LengthAwarePaginator
    {
        return $this->list($opts);
    }


    public function listForZapierAppPolling(string $triggerType): Collection
    {
        $opts = [
            'limit' => 5,
            'sort' => 'date_desc',
            'filters' => ['zapier_trigger_type' => $triggerType],
            'with' => [
                'tags',
                'user',
                'notes',
                'status',
                'client',
                'landing',
                'leadContacts',
                'acquisitionChannel',
                'leadCustomFieldsValues',
                'client.leadsCustomFields',
                'leadContacts.leadContactEmails',
                'leadContacts.leadContactPhones',
            ],
        ];
        $leads = $this->list($opts);
        return $leads->getCollection();
    }


    public function listToListPhones(array $opts): Collection
    {
        $opts['limit'] = 9999;
        $opts['with'] = ['leadContactPhones'];
        $response = $this->list($opts);
        return $response->getCollection();
    }


    public function quickSearch(array $opts): Collection
    {
        $client = $this->getClient();
        $response = $this->leadRepository->listPaginated($client, $opts);
        return $response->getCollection();
    }


    public function listIds(array $options): array
    {
        $options['filters'] = $this->getFilterCriteriasByName($options['filters'] ?? []);
        $ids = $this->leadRepository->listIds($this->getClient(), $options);
        return $ids;
    }


    public function listIdsByClientAndEmail(Client $client, string $email): Collection
    {
        $ids = $this->leadRepository->listIdsByClientAndEmail($client, $email);
        return $ids;
    }


    public function listIdsByClientAndPhone(Client $client, string $phone): Collection
    {
        $ids = $this->leadRepository->listIdsByClientAndPhone($client, $phone);
        return $ids;
    }


    public function findLeadAndSalesGroupedByChannels(
        ?DateTime $startDate = null,
        ?DateTime $endDate = null,
        array $filters = []
    ): ?Collection {
        return $this->leadRepository->findLeadAndSalesByClientGroupedByChannels(
            $this->getClient(), $startDate, $endDate, $filters
        );
    }

    
    public function findLeadAndProposalsGroupedByChannels(
        ?DateTime $startDate = null,
        ?DateTime $endDate = null,
        array $filters = []
    ): ?Collection {
        return $this->leadRepository->findLeadAndProposalsGroupedByChannels(
            $this->getClient(), $startDate, $endDate, $filters
        );
    }


    public function findLeadAndQualityGroupedByChannels(
        ?DateTime $startDate = null,
        ?DateTime $endDate = null,
        array $filters = []
    ): ?Collection {
        return $this->leadRepository->findLeadAndQualityGroupedByChannels(
            $this->getClient(), $startDate, $endDate, $filters
        );
    }


    private function getFilterCriteriasByName($filters)
    {
        $criterias = [
            'id' => LeadIdCriteria::class,
            'utm' => LeadUTMCriteria::class,
            'lead_type' => LeadTypeCriteria::class,
            'landing_id' => LandingCriteria::class,
            'quality' => LeadQualityCriteria::class,
            'only_utm_leads' => OnlyUTMLeadsCriteria::class,
            'custom_field' => LeadCustomFieldCriteria::class,
            'created_date_end' => CreatedDateEndCriteria::class,
            'created_date_start' => CreatedDateStartCriteria::class,
            'zapier_trigger_type' => ZapierTriggerTypeCriteria::class,
            'acquisition_channel_id' => AcquisitionChannelCriteria::class,
        ];

        $specialFilterValues = $filters['special_filter'] ?? null;
        if ($specialFilterValues) {
            $filters['special_filter'] = new SpecialFilterCriteria($specialFilterValues, $this->getUser());
        }

        if ($filters['tag_id'] ?? null) {
            if ($filters['tag_filter_type'] ?? null) {
                $tagFilterType = $filters['tag_filter_type'];
            } else {
                $tagFilterType = $this->getClient()->clientSettings->default_tag_filter;
            }

            if ($tagFilterType == 'or') {
                $tagCriteria = TagORCriteria::class;
            } else if ($tagFilterType == 'or_exclusive') {
                $tagCriteria = TagORExclusiveCriteria::class;
            } else if ($tagFilterType == 'and_exclude') {
                $tagCriteria = TagANDExcludeCriteria::class;
            } else if ($tagFilterType == 'or_exclude') {
                $tagCriteria = TagORExcludeCriteria::class;
            } else {
                $tagCriteria = TagANDCriteria::class;
            }

            $criterias['tag_id'] = $tagCriteria;
        }
        unset($filters['tag_filter_type']);

        $nfilters = [];
        foreach ($filters as $key => $value) {
            if ($value) {
                if (in_array($key, array_keys($criterias))) {
                    $nfilters[$key] = new $criterias[$key]($value);
                } else {
                    $nfilters[$key] = $value;
                }
            }
        }
        return $nfilters;
    }


    private function getSortCriteriasByName($sortsName)
    {
        $sortTypes = [
            'date_asc' => new SortByCreated('asc'),
            'date_desc' => new SortByCreated('desc'),
            'last_status_changed_date_asc' => new SortByLastStatusChanged('asc'),
            'last_status_changed_date_desc' => new SortByLastStatusChanged('desc'),
        ];
        return $sortsName ? $sortTypes[$sortsName] : $sortsName;
    }

}
