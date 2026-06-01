<?php

namespace App\Services\API;

use Exception;
use App\Models\News;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Repositories\NewsRepository;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\NewsNotificationRepository;
use App\Repositories\Criteria\Sort\News\SortByCreated;
use App\Repositories\Criteria\Filter\News\DateEndCriteria;
use App\Repositories\Criteria\Filter\News\DateStartCriteria;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Repositories\Criteria\Filter\News\VisibleClientCriteria;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;


class NewsService
{

    use GetClientFromRequest;

    private $newsRepository;
    private $newsNotificationService;


    public function __construct(
        NewsRepository $newsRepository,
        NewsNotificationService $newsNotificationService
    ) {
        $this->newsRepository = $newsRepository;
        $this->newsNotificationService = $newsNotificationService;
    }


    public function list(array $opts = []): LengthAwarePaginator
    {
        $repoOpts = [
            'page' => $opts['page'] ?? 1,
            'with' => $opts['with'] ?? [],
            'limit' => $opts['limit'] ?? 20,
            'order' => $this->getSortCriteriasByName($opts['sort'] ?? ''),
            'filters' => $this->getFilterCriteriasByName($opts['filters'] ?? []),
        ];
        $response = $this->newsRepository->listPaginated($repoOpts);
        return $response;
    }


    public function clientList(Client $client, array $opts = []): LengthAwarePaginator
    {
        $opts['filters'] = $opts['filters'] ?? [];
        // Para aplicar VisibleClientCriteria
        $opts['filters']['client_id'] = $client->id;
        return $this->list($opts);
    }


    public function listClientyConfigurationList(array $opts = []): LengthAwarePaginator
    {
        return $this->list($opts);
    }


    public function findNotViewedNewsNotificationsByUser(array $opts = []): LengthAwarePaginator
    {
        return $this->list($opts);
    }


    public function find(int $newsId): ?News
    {
        $news = $this->newsRepository->find($newsId);
        return $news;
    }


    public function createNewClientDefaultNotifications(Client $client): Collection
    {
        $createdNewsList = new Collection();
        $newsList = $this->newsRepository->findFutureClientsApplied();
        foreach ($newsList as $news) {
            $createdNews = $this->newsNotificationService->createForAllUsersByNewsAndClientIds(
                $news, [$client->id], ['isNotificationViewed' => true]
            );
            $createdNewsList->push($createdNews);
        }
        return $createdNewsList;
    }


    public function createWithNotifications(Client $client, array $newsData, array $newsNotificationsData): News
    {
        try {
            DB::beginTransaction();
            $newsData['client_id'] = $client->id;
            $news = $this->create($newsData);
            $this->newsNotificationService->createForAllUsersByNewsAndClientIds(
                $news, $newsNotificationsData['client_id']
            );
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $news;
    }



    public function updateWithNotifications(
        Client $client,
        News $news,
        array $newsData,
        array $newsNotificationsData
    ): News {
        try {
            DB::beginTransaction();
            $updatedNews = $this->update($news, $newsData);
            $this->newsNotificationService->updateForAllUsersByNewsAndClientIds(
                $updatedNews, $newsNotificationsData['client_id']
            );
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $updatedNews->fresh();
    }


    public function deleteWithNotifications(News $news): News
    {
        try {
            DB::beginTransaction();
            $deletedNews = $this->delete($news);
            $this->newsNotificationService->deleteAllByNews($news);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $deletedNews;
    }


    public function create(array $data): News
    {
        $news = $this->newsRepository->create($data);
        return $news;
    }


    public function update(News $news, array $data): News
    {
        $updated = $this->newsRepository->update($news, $data);
        return $updated;
    }


    public function delete(News $news): News
    {
        $deleted = $this->newsRepository->delete($news);
        return $deleted;
    }


    protected function getFilterCriteriasByName(array $filters): array
    {
        $nfilters = [];
        $criterias = [
            'client_id' => VisibleClientCriteria::class,
            'created_date_end' => DateEndCriteria::class,
            'created_date_start' => DateStartCriteria::class,
        ];

        foreach ($filters as $key => $value) {
            if (in_array($key, array_keys($criterias)) && $value !== null) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] = $value;
            }
        }

        return $nfilters;
    }


    private function getSortCriteriasByName($sortsName)
    {
        $sortTypes = ['date_asc' => new SortByCreated('asc'), 'date_desc' => new SortByCreated('desc')];
        return $sortsName ? $sortTypes[$sortsName] : $sortsName;
    }

}
