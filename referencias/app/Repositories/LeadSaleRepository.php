<?php

namespace App\Repositories;

use Closure;
use DateTime;
use Exception;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class LeadSaleRepository implements Repository
{

    public function findAllByClient(Client $client, array $options = []): Collection
    {
        $queryBuilder = LeadSale::where('client_id', $client->id);
        $queryBuilder = $this->addQueryBuilderOptions($queryBuilder, $options);
        $leadSales = $queryBuilder->get();
        return $leadSales;
    }


    public function list(Client $client, array $options = []): Collection
    {
        $limit = $options['limit'] ?? null;
        $filters = $options['filters'] ?? [];
        $order = $options['order'] ?? 'sale_date DESC';

        $queryBuilder = LeadSale::where('client_id', $client->id);
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $filters);
        $queryBuilder = $this->addQueryBuilderOptions($queryBuilder, $options);
        $queryBuilder->orderByRaw($order);
        
        if ($limit) {
            $queryBuilder->limit($limit);
        }

        $fields = $options['fields'] ?? ['*'];
        return $queryBuilder->get($fields);
    }


    public function listPaginated(Client $client, array $options = []): LengthAwarePaginator
    {
        $limit = $options['limit'] ?? 20;
        $pageNumber = $options['page'] ?? 1;
        $filters = $options['filters'] ?? [];
        $order = $options['order'] ?? 'sale_date DESC';

        $queryBuilder = LeadSale::where('client_id', $client->id);
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $filters);
        $queryBuilder = $this->addQueryBuilderOptions($queryBuilder, $options);
        $queryBuilder->orderByRaw($order);

        return $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
    }


    public function listPaginatedByCliendAndLeadIds(
        Client $client,
        array $leadIds,
        array $options = []
    ): LengthAwarePaginator {
        $limit = $options['limit'] ?? 20;
        $filters = $options['filters'] ?? [];
        $pageNumber = $options['page'] ?? 1;
        $order = $options['order'] ?? 'sale_date DESC';

        $queryBuilder = LeadSale::where('client_id', $client->id)->whereIn('lead_id', $leadIds);
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $filters);
        $queryBuilder = $this->addQueryBuilderOptions($queryBuilder, $options);
        $queryBuilder->orderByRaw($order);

        // $sql = $queryBuilder->toSql();
        // $bindings = $queryBuilder->getBindings();
        // dump("SQL: {$sql}");
        // dump("Bindings: " . implode(', ', $bindings));

        return $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
    }


    public function listByClientAndLeadIds(Client $client, array $leadIds, array $options = []): Collection
    {
        $limit = $options['limit'] ?? 20;
        $filters = $options['filters'] ?? [];
        $order = $options['order'] ?? 'sale_date DESC';

        $queryBuilder = LeadSale::where('client_id', $client->id)->whereIn('lead_id', $leadIds);
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $filters);
        $queryBuilder = $this->addQueryBuilderOptions($queryBuilder, $options);
        $queryBuilder->orderByRaw($order);

        return $queryBuilder->get();
    }


    public function findByClientAndDates(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd,
        array $options = []
    ): Collection {
        $queryBuilder = LeadSale::where('client_id', $client->id)
            ->whereDate('sale_date', '>=', $dateStart->format('Y-m-d'))
            ->whereDate('sale_date', '<=', $dateEnd->format('Y-m-d'))
        ;
        $queryBuilder = $this->addQueryBuilderOptions($queryBuilder, $options);
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $options['filters'] ?? []);
        $leadSales = $queryBuilder->get();
        return $leadSales;
    }


    public function findByClientAndDatesGroupingByLeadDisctinct(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd
    ): Collection {
        $leadSales = LeadSale::where('client_id', $client->id)
            ->whereDate('sale_date', '>=', $dateStart->format('Y-m-d'))
            ->whereDate('sale_date', '<=', $dateEnd->format('Y-m-d'))
            ->groupBy('lead_id')
            ->distinct()
            ->get()
        ;
        return $leadSales;
    }


    public function findLastSaleByLead(Lead $lead): LeadSale
    {
        return LeadSale::where('lead_id', $lead->id)->orderBy('id', 'DESC')->first();
    }


    public function findByClientAndLeads(Client $client, Collection $leads, array $opts = []): Collection
    {
        $fields = $opts['fields'] ?? ['*'];
        return LeadSale::where('client_id', $client->id)->whereIn('lead_id', $leads->pluck('id'))->get($fields);
    }


    public function create($data): LeadSale
    {
        $leadSale = new LeadSale($data);
        $leadSale->saveOrFail();
        return $leadSale->fresh();
    }


    public function update(LeadSale $leadSale, array $data): LeadSale
    {
        $leadSale->fill($data);
        $leadSale->saveOrFail();
        return $leadSale->fresh();
    }


    public function delete(LeadSale $leadSale): LeadSale
    {
        $leadSale->delete();
        return $leadSale->fresh();
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
