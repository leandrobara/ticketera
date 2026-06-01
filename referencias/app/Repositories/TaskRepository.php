<?php

namespace App\Repositories;

use Exception;
use App\Models\User;
use App\Models\Task;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Helpers\MongoSearchHelper;
use App\Exceptions\DatabaseException;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Traits\VoidClearCache;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class TaskRepository implements Repository
{

    use VoidClearCache;


    private $mongoSearchHelper;


    public function __construct(MongoSearchHelper $mongoSearchHelper)
    {
        $this->mongoSearchHelper = $mongoSearchHelper;
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return Task::whereIn('id', $ids)->where('client_id', $client->id)->get();
    }


    public function findPaginatedByFiltersAndClient(Client $client, array $opts = []): LengthAwarePaginator
    {
        $limit = $opts['limit'] ?? 20;
        $pageNumber = $opts['page'] ?? 1;
        $queryBuilder = $this->buildFindQueryBuilder($opts, $client);
        // DB::enableQueryLog();
        $tasks = $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
        // dd(DB::getQueryLog());
        return $tasks;
    }


    public function searchPaginatedByFiltersAndClient(string $searchTerm, Client $client, array $opts = [])
    {
        $limit = $opts['limit'] ?? 20;
        $pageNumber = $opts['page'] ?? 1;
        
        $searchOpts = ['filters' => ['client_id' => $client->id], 'fields' => ['id', 'full_names']];
        $leadDocs = $this->mongoSearchHelper->search($searchTerm, $searchOpts);
        $leadIds = $leadDocs->map(function ($leadDoc) {
            return (int) $leadDoc['id'];
        });
        $leadIds = $leadIds->push(-1)->values()->toArray();
        
        $opts['filters']['lead_id'] = $leadIds;
        $queryBuilder = $this->buildFindQueryBuilder($opts, $client);
        return $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
    }


    public function findByFiltersAndClient(Client $client, array $opts = []): Collection
    {
        $limit = $opts['limit'] ?? null;
        $queryBuilder = $this->buildFindQueryBuilder($opts, $client);
        if ($limit) {
            $queryBuilder->limit($limit);
        }
        $tasks = $queryBuilder->get();
        return $tasks;
    }


    public function findByFiltersAndUser(User $user, array $opts = []): Collection
    {
        $limit = $opts['limit'] ?? null;
        $order = $opts['sort'] ?? [];
        $filters = $opts['filters'] ?? [];
        $relationshipsToEagerLoad = $opts['with'] ?? [];

        $queryBuilder = Task::query();
        $queryBuilder->where('user_id', $user->id);

        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }

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
        if ($order) {
            $queryBuilder->orderByRaw($order);
        }
        if ($limit) {
            $queryBuilder->limit($limit);
        }
        // DB::enableQueryLog();
        $tasks = $queryBuilder->get();
        // dump(DB::getQueryLog());
        return $tasks;
    }


    protected function buildFindQueryBuilder(array $opts = [], ?Client $client = null): Builder
    {
        $order = $opts['order'] ?? [];
        $filters = $opts['filters'] ?? [];
        $relationshipsToEagerLoad = $opts['with'] ?? [];

        $queryBuilder = Task::query();
        if ($client) {
            $queryBuilder->where('client_id', $client->id);
        }
        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }

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

        if ($order) {
            if (is_a($order, SortCriteria::class)) {
                $queryBuilder = $order->applySort($queryBuilder);
            } else {
                $queryBuilder->orderByRaw($order);
            }
        }

        return $queryBuilder;
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


    public function create(array $data): Task
    {
        $task = new Task($data);
        $task->saveOrFail();
        return $task->fresh();
    }


    public function update(Task $task, array $data): Task
    {
        $task->fill($data);
        $task->saveOrFail();
        return $task->fresh();
    }


    public function delete(Task $task): Task
    {
        $task->delete();
        return $task->fresh();
    }


    public function updateMassive(Collection $taskIds, array $attributes): bool
    {
        Task::whereIn('id', $taskIds)->update($attributes);
        return true;
    }


    public function deleteMassive(Collection $taskIds): bool
    {
        Task::whereIn('id', $taskIds)->delete();
        return true;
    }


    public function countPendingByClient(Client $client)
    {
        $count = Task::where('client_id', $client->id)->where('status', 'pending')->count();
        return $count;
    }
    

    public function countPendingByUser(User $user)
    {
        $count = Task::where('client_id', $user->client_id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->count()
        ;
        return $count;
    }


    public function listIds(Client $client, array $opts = []): array
    {
        $searchTerm = $opts['filters']['search'] ?? null;
        unset($opts['filters']['search']);
        
        $filters = $opts['filters'] ?? [];
        $queryBuilder = Task::where('client_id', $client->id);
        $queryBuilder = $this->applyFilters($queryBuilder, $filters);
        
        if ($searchTerm) {
            $searchOpts = ['filters' => ['client_id' => $client->id], 'fields' => ['id', 'full_names']];
            $leadDocs = $this->mongoSearchHelper->search($searchTerm, $searchOpts);
            $taskIds = $leadDocs->map(function ($leadDoc) {
                return (int) $leadDoc['id'];
            });
            $taskIds = $taskIds->push(-1)->values()->toArray();
            $queryBuilder->whereIn('id', $taskIds);
        }
        return $queryBuilder->select(['id', 'lead_id'])->get()->toArray();
    }

}
