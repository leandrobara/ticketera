<?php

namespace App\Repositories;

use Exception;
use App\Models\News;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Traits\VoidClearCache;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class NewsRepository
{

    use VoidClearCache;


    public function listPaginated(array $opts = []): LengthAwarePaginator
    {
        $limit = $opts['limit'] ?? 20;
        $order = $opts['order'] ?? null;
        $pageNumber = $opts['page'] ?? 1;
        $filters = $opts['filters'] ?? [];

        $queryBuilder = News::query();
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
        $result = $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
        return $result;
    }


    public function find(int $newsId): ?News
    {
        $news = News::find($newsId);
        return $news;
    }


    public function findFutureClientsApplied(): Collection
    {
        $news = News::where('apply_to_future_clients', true)->get();
        return $news;
    }


    public function create(array $data): News
    {
        $news = new News($data);
        $news->saveOrFail();
        return $news->fresh();
    }


    public function update(News $news, array $data): News
    {
        $news->fill($data);
        $news->saveOrFail();
        return $news->fresh();
    }


    public function delete(News $news): News
    {
        $news->delete();
        return $news->fresh();
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
