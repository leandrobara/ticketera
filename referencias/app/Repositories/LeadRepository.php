<?php

namespace App\Repositories;

use DateTime;
use Exception;
use App\Models\Tag;
use App\Models\User;
use App\Models\Lead;
use App\Models\LeadSale;
use App\Models\Status;
use App\Models\Client;
use App\Models\LeadContactEmail;
use App\Models\LeadContactPhone;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Helpers\MongoSearchHelper;
use App\Exceptions\DatabaseException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class LeadRepository
{

    private $mongoSearchHelper;


    public function __construct(MongoSearchHelper $mongoSearchHelper)
    {
        $this->mongoSearchHelper = $mongoSearchHelper;
    }


    public function listPaginated(Client $client, array $opts = []): LengthAwarePaginator
    {
        $searchTerm = $opts['filters']['search'] ?? null;
        unset($opts['filters']['search']);

        $limit = $opts['limit'] ?? 30;
        $order = $opts['order'] ?? null;
        $pageNumber = $opts['page'] ?? 1;
        $filters = $opts['filters'] ?? [];
        $relationshipsToEagerLoad = $opts['with'] ?? [];
        
        $queryBuilder = Lead::where('client_id', $client->id);
        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }
        $queryBuilder = $this->applyFilters($queryBuilder, $filters);

        if ($searchTerm) {
            if ($this->searchTermHasFindByLeadIdsClause($searchTerm)) {
                $leadIds = $this->getLeadIdsFromSearchTerm($searchTerm);
            } else {
                $leadIds = $this->getLeadIdsFromMongoSearch($client, $searchTerm, $opts);
                $leadIdsStr = implode(',', $leadIds);
                $order = DB::raw("FIELD(id, $leadIdsStr)");
            }
            $queryBuilder->whereIn('id', $leadIds);
        }
        
        if ($order) {
            if (is_a($order, SortCriteria::class)) {
                $queryBuilder = $order->applySort($queryBuilder);
            } else {
                $queryBuilder->orderByRaw($order);
            }
        }
        // DB::enableQueryLog();
        $result = $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
        // dd(DB::getQueryLog());
        return $result;
    }



    public function listIds(Client $client, array $opts = []): array
    {
        $searchTerm = $opts['filters']['search'] ?? null;
        unset($opts['filters']['search']);
        
        $filters = $opts['filters'] ?? [];
        $queryBuilder = Lead::where('client_id', $client->id);
        $queryBuilder = $this->applyFilters($queryBuilder, $filters);
        
        if ($searchTerm) {
            if ($this->searchTermHasFindByLeadIdsClause($searchTerm)) {
                $leadIds = $this->getLeadIdsFromSearchTerm($searchTerm);
            } else {
                $leadIds = $this->getLeadIdsFromMongoSearch($client, $searchTerm, $opts);
                $leadIdsStr = implode(',', $leadIds);
            }
            $queryBuilder->whereIn('id', $leadIds);
        }

        return $queryBuilder->select(['id'])->get()->pluck('id')->toArray();
    }


    public function listIdsByClientAndEmail(Client $client, string $email): Collection
    {
        $queryBuilder = Lead::where('client_id', $client->id)
            ->whereHas('leadContactEmails', function ($q) use ($email) {
                $q->where('hash', LeadContactEmail::buildHash($email));
            })->select(['id']);
        ;
        return $queryBuilder->get()->pluck('id');
    }


    public function listIdsByClientAndPhone(Client $client, string $phone): Collection
    {
        $queryBuilder = Lead::where('client_id', $client->id)
            ->whereHas('leadContactPhones', function ($q) use ($phone) {
                $q->where('hash', LeadContactPhone::buildHash($phone));
            })->select(['id']);
        ;
        return $queryBuilder->get()->pluck('id');
    }


    public function store(array $attrs): Lead
    {
        $lead = new Lead();
        $lead->fill($attrs);
        $lead->saveOrFail();
        return $lead->fresh();
    }


    public function update(Lead $lead, array $newAttrs): Lead
    {
        $updatedLead = clone $lead;
        $updatedLead->fill($newAttrs);
        $updatedLead->save();
        return $updatedLead->fresh();
    }


    public function findByIds(Collection $leadIds, array $opts = []): Collection
    {
        $with = $opts['with'] ?? [];
        $fields = $opts['fields'] ?? [];
        $getRawResult = $opts['getRawResult'] ?? false;
        
        $queryBuilder = Lead::query();
        if ($getRawResult) {
            $queryBuilder = DB::table('Leads')->whereNull('deleted_at');
        }

        $queryBuilder->whereIn('id', $leadIds);
        if ($fields) {
            $queryBuilder->select($fields);
        }
        if ($with) {
            $queryBuilder->with($with);
        }
        return $queryBuilder->get();
    }


    public function findByClientAndIds(Client $client, Collection $leadIds, array $opts = []): Collection
    {
        $with = $opts['with'] ?? [];
        $fields = $opts['fields'] ?? [];
        $getRawResult = $opts['getRawResult'] ?? false;
        
        $queryBuilder = Lead::where('client_id', $client->id);
        if ($getRawResult) {
            $queryBuilder = DB::table('Leads')->whereNull('deleted_at');
        }

        $queryBuilder->whereIn('id', $leadIds);
        if ($fields) {
            $queryBuilder->select($fields);
        }
        if ($with) {
            $queryBuilder->with($with);
        }
        return $queryBuilder->get();
    }


    public function changeMassiveLeadsStatus(Status $originalStatus, Status $newStatus): array
    {
        $leadIds = Lead::where('status_id', $originalStatus->id)->select('id')->get()->pluck('id')->toArray();
        if (!$leadIds) {
            return [];
        }
        Lead::whereIn('id', $leadIds)->update([
            'status_id' => $newStatus->id,
            'last_status_changed_at' => new DateTime(),
        ]);
        return $leadIds;
    }


    public function setMassiveLeadsStatus(Collection $leads, Status $newStatus): Collection
    {
        $leadIds = $leads->pluck('id');
        Lead::whereIn('id', $leadIds)->update([
            'status_id' => $newStatus->id,
            'last_status_changed_at' => new DateTime(),
        ]);
        return $leadIds;
    }


    public function setMassiveLeadsAcquisitionChannel(
        Collection $leads,
        AcquisitionChannel $newAcquisitionChannel
    ): Collection {
        $leadIds = $leads->pluck('id');
        Lead::whereIn('id', $leadIds)->update(['acquisition_channel_id' => $newAcquisitionChannel->id]);
        return $leadIds;
    }


    public function setMassiveLeadsUser(Collection $leads, User $newUser): Collection
    {
        $leadIds = $leads->pluck('id');
        Lead::whereIn('id', $leadIds)->update(['user_id' => $newUser->id]);
        return $leadIds;
    }


    public function editMassiveLeadsTags(Collection $leads, Collection $tags, array $opts = []): Collection
    {
        $syncMethod = 'syncWithoutDetaching';
        
        if ($opts['assignType'] == 'add') {
            $syncMethod = 'syncWithoutDetaching';
        }
        if ($opts['assignType'] == 'replace') {
            $syncMethod = 'sync';
        }
        if ($opts['assignType'] == 'remove') {
            $syncMethod = 'detach';
        }

        $tagsIds = $tags->pluck('id');
        foreach ($leads as $lead) {
            $lead->tags()->$syncMethod($tagsIds);
            $lead->saveOrFail();
        }
        return $leads->pluck('id');
    }


    public function setLeadTags(Lead $lead, Collection $tags)
    {
        $lead->tags()->sync($tags->pluck('id')->unique());
        $lead->saveOrFail();
        return $lead->fresh();
    }


    public function changeMassiveLeadsAcquisitionChannel(
        AcquisitionChannel $originalChannel,
        AcquisitionChannel $newChannel
    ): array {
        $leadIds = Lead::where('acquisition_channel_id', $originalChannel->id)
            ->select('id')
            ->get()
            ->pluck('id')
            ->toArray()
        ;
        if (!$leadIds) {
            return [];
        }
        Lead::whereIn('id', $leadIds)->update(['acquisition_channel_id' => $newChannel->id]);
        return $leadIds;
    }


    public function findOneByLeadsId(int $leadsLeadId, array $opts = []): ?Lead
    {
        $queryBuilder = Lead::where('leads_query_id', $leadsLeadId);
        if ($opts['withTrashed'] ?? false) {
            $queryBuilder->withTrashed();
        }
        return $queryBuilder->first();
    }


    public function findOneByClientAndHash(Client $client, string $hash, array $opts = []): ?Lead
    {
        $builder = Lead::where('client_id', $client->id)->where('hash', $hash);
        if ($opts['withTrashed'] ?? false) {
            $builder->withTrashed();
        }
        return $builder->first();
    }


    public function findByClientAndHash(Client $client, string $hash, array $opts = []): Collection
    {
        $builder = Lead::where('client_id', $client->id)->where('hash', $hash);
        if ($opts['withTrashed'] ?? false) {
            $builder->withTrashed();
        }
        return $builder->get();
    }


    public function findLastLeadByClient(Client $client): ?Lead
    {
        return Lead::where('client_id', $client->id)->orderBy('id', 'DESC')->first();
    }


    public function findByClientWithNoTags(Client $client, array $opts = []): Collection
    {
        $fields = $opts['fields'] ?? ['*'];
        $leads = Lead::where('client_id', $client->id)->whereDoesntHave('tags')->get($fields);
        return $leads;
    }


    public function findLeadAndSalesByClientGroupedByChannels(
        Client $client,
        ?Datetime $dateStart,
        ?Datetime $dateEnd,
        array $filters = []
    ): ?Collection {
        $builder = Lead::select(
            DB::raw('Leads.acquisition_channel_id as acquisition_channel_id'),
            DB::raw('COUNT(DISTINCT(Leads.id)) as leads_count'),
            DB::raw('COUNT(LeadsSales.id) as total_sales_count'),
            DB::raw('COUNT(DISTINCT(LeadsSales.lead_id)) as unique_sales_count')
        );

        if ($filters['user_id'] ?? []) {
            $builder->whereIn('Leads.user_id', $filters['user_id']);
        }

        $builder->leftJoin('LeadsSales', 'Leads.id', '=', 'LeadsSales.lead_id')
            ->join('Status', 'Status.id', '=', 'Leads.status_id')
            ->join('StatusCategories', 'StatusCategories.id', '=', 'Status.status_category_id')
            ->where('StatusCategories.is_irrelevant', false)
            ->where('Leads.client_id', $client->id)
            ->groupBy('Leads.acquisition_channel_id')
        ;
        $builder->whereNull('LeadsSales.deleted_at');

        if ($dateStart) {
            $builder->whereRaw(
                "DATE(Leads.lead_created_at) >= '{$dateStart->format('Y-m-d')}'"
            );
        }
        if ($dateEnd) {
            $builder->whereRaw(
                "DATE(Leads.lead_created_at) <= '{$dateEnd->format('Y-m-d')}'"
            );
        }
        return $builder->get();
    }


    public function findLeadAndProposalsGroupedByChannels(
        Client $client,
        ?Datetime $dateStart,
        ?Datetime $dateEnd,
        array $filters = []
    ): ?Collection {
        $builder = Lead::select(
            DB::raw('Leads.acquisition_channel_id as acquisition_channel_id'),
            DB::raw('COUNT(DISTINCT(Leads.id)) as leads_count'),
            DB::raw('COUNT(ProposalsInfo.id) as total_proposals_count'),
            DB::raw('COUNT(DISTINCT(ProposalsInfo.lead_id)) as unique_proposals_count')
        );

        if ($filters['user_id'] ?? []) {
            $builder->whereIn('Leads.user_id', $filters['user_id']);
        }

        $builder->leftJoin('ProposalsInfo', 'Leads.id', '=', 'ProposalsInfo.lead_id')
            ->join('Status', 'Leads.status_id', '=', 'Status.id')
            ->join('StatusCategories', 'StatusCategories.id', '=', 'Status.status_category_id')
            ->where('Leads.client_id', $client->id)
            ->where('StatusCategories.is_irrelevant', false)
            ->groupBy('Leads.acquisition_channel_id')
        ;
        $builder->whereNull('ProposalsInfo.deleted_at');

        if ($dateStart) {
            $builder->whereRaw(
                "DATE(Leads.lead_created_at) >= '{$dateStart->format('Y-m-d')}'"
            );
        }
        if ($dateEnd) {
            $builder->whereRaw(
                "DATE(Leads.lead_created_at) <= '{$dateEnd->format('Y-m-d')}'"
            );
        }
        return $builder->get();
    }


    public function findLeadAndQualityGroupedByChannels(
        Client $client,
        ?Datetime $dateStart,
        ?Datetime $dateEnd,
        array $filters = []
    ): ?Collection {
        $builder =  Lead::select(
            DB::raw('Leads.acquisition_channel_id AS acquisition_channel_id'),
            DB::raw('COUNT(*) AS leads_count'),
            DB::raw('COUNT(DISTINCT CASE WHEN quality >= 2 THEN 1 ELSE 0 END) AS quality_leads_count')
        );
        
        if ($filters['user_id'] ?? []) {
            $builder->whereIn('Leads.user_id', $filters['user_id']);
        }

        $builder
            ->join('Status', 'Leads.status_id', '=', 'Status.id')
            ->join('StatusCategories', 'StatusCategories.id', '=', 'Status.status_category_id')
            ->where('Leads.client_id', $client->id)
            ->where('StatusCategories.is_irrelevant', false)
            ->groupBy('Leads.acquisition_channel_id')
        ;

        if ($dateStart) {
            $builder->whereRaw(
                "DATE(Leads.lead_created_at) >= '{$dateStart->format('Y-m-d')}'"
            );
        }
        if ($dateEnd) {
            $builder->whereRaw(
                "DATE(Leads.lead_created_at) <= '{$dateEnd->format('Y-m-d')}'"
            );
        }
        return $builder->get();
    }


    protected function applyFilters(object $queryBuilder, array $filters): object
    {
        foreach ($filters as $key => $value) {
            if (isset($filters[$key])) {
                if (is_array($value)) {
                    $queryBuilder->whereIn($key, $value);
                } elseif ($filters[$key] instanceof SQLFilterCriteria) {
                    $queryBuilder = $filters[$key]->filterSQLQuery($queryBuilder);
                } else {
                    $queryBuilder->where($key, $value);
                }
            }
        }
        return $queryBuilder;
    }


    public function findAllUTMCampaigns(Client $client): Collection
    {
        $utmCampaigns = DB::table('Leads')
            ->distinct()
            ->whereNull('deleted_at')
            ->select(['utm_campaign'])
            ->where('client_id', $client->id)
            ->get(['utm_campaign'])
        ;
        return $utmCampaigns->pluck('utm_campaign');
    }


    private function getLeadIdsFromMongoSearch(Client $client, string $searchTerm, array $opts): array
    {
        $searchOpts = [
            'offset' => 0,
            'limit' => 999,
            'fields' => ['id', 'full_names'],
            'filters' => ['client_id' => $client->id],
        ];
        $leadDocs = $this->mongoSearchHelper->search($searchTerm, $searchOpts);
        $leadIds = $leadDocs->map(fn ($leadDoc) => (int) $leadDoc['id']);
        $leadIds = $leadIds->push(-1)->values()->toArray();
        return $leadIds;
    }


    private function getLeadIdsFromSearchTerm(string $searchTerm): array
    {
        $searchTerm = strtolower(trim($searchTerm));
        $idsStr = str_replace('id:', '', $searchTerm);
        $leadIds = collect(explode(',', $idsStr));
        $leadIds->map(fn ($leadId) => (int) $leadId)->filter(fn ($leadId) => $leadId)->push(-1);
        return $leadIds->values()->toArray();
    }


    private function searchTermHasFindByLeadIdsClause(string $searchTerm): bool
    {
        return strpos(strtolower(trim($searchTerm)), 'id:') === 0;
    }
}
