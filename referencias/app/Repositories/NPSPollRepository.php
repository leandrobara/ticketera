<?php

namespace App\Repositories;

use Exception;
use App\Models\User;
use App\Models\Client;
use App\Models\NPSPoll;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Traits\VoidClearCache;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class NPSPollRepository implements Repository
{

    use VoidClearCache;


    public function list(array $opts = []): Collection
    {
        $order = $opts['order'] ?? null;
        $filters = $opts['filters'] ?? [];

        $queryBuilder = NPSPoll::query();
        $queryBuilder = $this->applyFilters($queryBuilder, $filters);

        if ($order) {
            if (is_a($order, SortCriteria::class)) {
                $queryBuilder = $order->applySort($queryBuilder);
            } else {
                $queryBuilder->orderByRaw($order);
            }
        }
        if ($options['with'] ?? []) {
            $queryBuilder->with($options['with']);
        }
        return $queryBuilder->get();
    }


    public function findOneOpenedByClient(Client $client): ?NPSPoll
    {
        $NPSPolls = NPSPoll::where('client_id', $client->id)->whereNull('closed_date')->first();
        return $NPSPolls;
    }


    // Trae la Poll y las respuestas que APUNTEN a cierto Client.
    // Recordar que client_id de Poll es siempre 2 (el ABM es de Clienty).
    public function findLastByTargetedClient(Client $client, array $opts = []): ?NPSPoll
    {
        $filters = $opts['filters'] ?? [];
        $queryBuilder = NPSPoll::whereHas('NPSPollAnswers', function ($q) use ($client) {
            $q->where('client_id', $client->id);
        });

        $type = $filters['type'] ?? null;
        if ($type) {
            $queryBuilder->where('type', $type);
        }
        $queryBuilder->orderBy('id', 'desc');
        return $queryBuilder->first();
    }


    public function findCurrentUnscoredByUser(User $user): ?NPSPoll
    {
        $NPSPolls = NPSPoll::whereNull('closed_date')
            ->whereHas('NPSPollAnswers', function ($q) use ($user) {
                $q->where('user_id', $user->id)->whereNull('closed_date')->whereNull('score');
            })
            ->first()
        ;
        return $NPSPolls;
    }


    public function find(int $NPSPollId): ?NPSPoll
    {
        $NPSPolls = NPSPoll::find($NPSPollId);
        return $NPSPolls;
    }


    public function create(array $data): NPSPoll
    {
        $NPSPoll = new NPSPoll($data);
        $NPSPoll->saveOrFail();
        return $NPSPoll->fresh();
    }


    public function update(NPSPoll $NPSPoll, array $data): NPSPoll
    {
        $NPSPoll->fill($data);
        $NPSPoll->saveOrFail();
        return $NPSPoll->fresh();
    }


    public function delete(NPSPoll $NPSPoll): NPSPoll
    {
        $NPSPoll->delete();
        return $NPSPoll->fresh();
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

}
