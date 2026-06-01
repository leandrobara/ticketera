<?php

namespace App\Repositories;

use DateTime;
use Exception;
use App\Models\News;
use App\Models\User;
use App\Models\Client;
use App\Models\NewsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Traits\VoidClearCache;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class NewsNotificationRepository implements Repository
{
    
    use VoidClearCache;


    public function findNotViewedByUser(User $user): Collection
    {
        return NewsNotification::where('user_id', $user->id)
            ->where('client_id', $user->client_id)
            ->where('is_notification_viewed', false)
            ->with([
                'news' => function ($query) {
                    $query->select('id', 'title', 'type', 'force_modal_show');
                }
            ])
            ->orderBy('updated_at', 'desc')
            ->get()
        ;
    }

    public function listPaginated(Client $client, User $user, array $options = []): LengthAwarePaginator
    {
        $limit = $options['limit'] ?? 20;
        $order = $options['order'] ?? null;
        $pageNumber = $options['page'] ?? 1;
        $filters = $options['filters'] ?? [];
        $relationshipsToEagerLoad = $options['with'] ?? [];
        
        $queryBuilder = NewsNotification::where('client_id', $client->id)->where('user_id', $user->id);
        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }
        $queryBuilder = $this->applyFilters($queryBuilder, $filters);
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


    public function create($data): NewsNotification
    {
        $newsNotification = new NewsNotification($data);
        $newsNotification->saveOrFail();
        return $newsNotification->fresh();
    }


    public function bulkInsert($data): bool
    {
        return NewsNotification::insert($data);
    }


    public function update(NewsNotification $newsNotification, array $data): NewsNotification
    {
        $newsNotification->fill($data);
        $newsNotification->saveOrFail();
        return $newsNotification->fresh();
    }


    public function delete(NewsNotification $newsNotification): NewsNotification
    {
        $newsNotification->delete();
        return $newsNotification->fresh();
    }


    public function deleteAllByNews(News $news): bool
    {
        $dateNow = new DateTime();
        $response = NewsNotification::where('news_id', $news->id)->update([
            'deleted_at_ts' => $dateNow->getTimestamp(),
            'deleted_at' => $dateNow->format('Y-m-d H:i:s'),
        ]);
        return $response;
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
