<?php

namespace App\Repositories;

use Closure;
use DateTime;
use Exception;
use App\Models\Lead;
use App\Models\Client;
use App\Models\ProposalInfo;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class ProposalInfoRepository
{

    public function findLastProposalFromLead(Lead $lead): ?ProposalInfo
    {
        return $this->findLastProposalFromLeadId($lead->id);
    }


    public function findLastProposalFromLeadId(int $leadId): ?ProposalInfo
    {
        return ProposalInfo::where('lead_id', $leadId)->orderBy('id', 'desc')->first();
    }


    public function findLastProposalFromLeadIdAndWhatsAppSendingId(int $leadId, int $whatsAppSendingId): ?ProposalInfo
    {
        return ProposalInfo::where('lead_id', $leadId)
            ->where('whatsapp_sending_id', $whatsAppSendingId)
            ->orderBy('id', 'desc')
            ->first()
        ;
    }


    public function listPaginated(Client $client, array $options = []): LengthAwarePaginator
    {
        $limit = $options['limit'] ?? 20;
        $pageNumber = $options['page'] ?? 1;
        $filters = $options['filters'] ?? [];
        $order = $options['order'] ?? 'sent_date DESC';

        $queryBuilder = ProposalInfo::where('client_id', $client->id);
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $filters);
        $queryBuilder = $this->addQueryBuilderOptions($queryBuilder, $options);
        $queryBuilder->orderByRaw($order);

        // $sql = $queryBuilder->toSql();
        // $bindings = $queryBuilder->getBindings();
        // dump("SQL: {$sql}");
        // dd("Bindings: " . implode(', ', $bindings));

        return $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
    }


    public function listPaginatedByCliendAndLeadIds(
        Client $client,
        array $leadIds,
        array $options = []
    ): LengthAwarePaginator {
        $limit = $options['limit'] ?? 20;
        $pageNumber = $options['page'] ?? 1;
        $filters = $options['filters'] ?? [];
        $order = $options['order'] ?? 'sent_date DESC';

        $queryBuilder = ProposalInfo::where('client_id', $client->id)->whereIn('lead_id', $leadIds);
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $filters);
        $queryBuilder = $this->addQueryBuilderOptions($queryBuilder, $options);
        $queryBuilder->orderByRaw($order);

        return $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
    }


    public function listByClientAndLeadIds(
        Client $client,
        array $leadIds,
        array $options = []
    ): Collection {
        $limit = $options['limit'] ?? 20;
        $filters = $options['filters'] ?? [];
        $order = $options['order'] ?? 'sent_date DESC';

        $queryBuilder = ProposalInfo::where('client_id', $client->id)->whereIn('lead_id', $leadIds);
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $filters);
        $queryBuilder = $this->addQueryBuilderOptions($queryBuilder, $options);
        $queryBuilder->orderByRaw($order);

        return $queryBuilder->get();
    }


    public function findAllByClient(Client $client, array $options = []): Collection
    {
        $queryBuilder = ProposalInfo::where('client_id', $client->id);
        $queryBuilder = $this->addQueryBuilderOptions($queryBuilder, $options);
        $proposalInfoList = $queryBuilder->get();
        return $proposalInfoList;
    }


    public function findProposalInfoByPeriod(
        Client $client,
        ?DateTime $dateStart = null,
        ?DateTime $dateEnd = null,
        array $options = []
    ): Collection {
        $queryBuilder = ProposalInfo::where('client_id', $client->id);
        $queryBuilder = $this->addQueryBuilderOptions($queryBuilder, $options);
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $options['filters'] ?? []);

        if ($dateStart) {
            $queryBuilder->where('sent_date', '>=', $dateStart->format('Y-m-d H:i:s'));
        }
        if ($dateEnd) {
            $queryBuilder->where('sent_date', '<=', $dateEnd->format('Y-m-d H:i:s'));
        }
        $result = $queryBuilder->get();
        return $result;
    }


    public function create($data): ProposalInfo
    {
        $proposalInfo = new ProposalInfo($data);
        $proposalInfo->saveOrFail();
        return $proposalInfo->fresh();
    }


    public function update(ProposalInfo $proposalInfo, array $data): ProposalInfo
    {
        $proposalInfo->fill($data);
        $proposalInfo->save();
        return $proposalInfo->fresh();
    }


    public function delete(ProposalInfo $proposalInfo): ProposalInfo
    {
        $proposalInfo->delete();
        return $proposalInfo->fresh();
    }


    private function addQueryBuilderFilters(Builder $queryBuilder, array $filters): Builder
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


    private function addQueryBuilderOptions(Builder $queryBuilder, array $options): Builder
    {
        if ($options['with'] ?? []) {
            $queryBuilder->with($options['with']);
        }
        if ($options['whereHas'] ?? []) {
            foreach ($options['whereHas'] as $index => $value) {
                if ($value instanceof Closure) {
                    $queryBuilder->whereHas($index, $value);
                } else {
                    $queryBuilder->whereHas($value);
                }
            }
        }
        return $queryBuilder;
    }

}
