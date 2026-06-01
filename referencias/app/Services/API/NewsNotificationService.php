<?php

namespace App\Services\API;

use DateTime;
use Exception;
use App\Models\News;
use App\Models\User;
use App\Models\Client;
use App\Models\NewsNotification;
use App\Repositories\Repository;
use App\Services\API\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\NewsNotificationRepository;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;


class NewsNotificationService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $userService;
    private $clientService;
    private $newsNotificationRepository;
    

    public function __construct(
        Repository $newsNotificationRepository,
        UserService $userService,
        ClientService $clientService
    ) {
        $this->userService = $userService;
        $this->clientService = $clientService;
        $this->newsNotificationRepository = $newsNotificationRepository;
    }


    public function findNotViewedByUser(User $user): Collection
    {
        return $this->newsNotificationRepository->findNotViewedByUser($user);

    }


    public function list(Client $client, array $opts = []): LengthAwarePaginator
    {
        $response = $this->newsNotificationRepository->listPaginated($this->getClient(), $this->getUser(), $opts);
        return $response;
    }


    public function createForAllUsersByNewsAndClientIds(News $news, array $clientIds, array $opts = []): bool
    {
        if (!$clientIds) {
            $clientIds = $this->clientService->findAllEnabled()->pluck('id');
        }
        $clientIds = collect($clientIds);

        $isNotificationViewed = $opts['isNotificationViewed'] ?? false;

        $dateNow = new Datetime();
        $bulkData = $this->userService
            ->findAllEnabledByClientIds($clientIds, ['fields' => ['id', 'client_id']])
            ->map(function ($u) use ($news, $dateNow, $isNotificationViewed) {
                return [
                    'user_id' => $u->id,
                    'news_id' => $news->id,
                    'created_at' => $dateNow,
                    'updated_at' => $dateNow,
                    'client_id' => $u->client_id,
                    'is_notification_viewed' => $isNotificationViewed,
                ];
            })
        ;

        $this->newsNotificationRepository->bulkInsert($bulkData->toArray());
        
        foreach ($clientIds as $clientId) {
            $this->newsNotificationRepository->clearCacheForClient($clientId);
        }
        return true;
    }


    public function updateForAllUsersByNewsAndClientIds(News $news, array $clientIds): bool
    {
        $oldClientIds = $news->newsNotifications->pluck('client_id')->unique();
        if (!$clientIds) {
            $clientIds = $this->clientService->findAllEnabled()->pluck('id')->toArray();
        }
        $this->newsNotificationRepository->deleteAllByNews($news);
        $this->createForAllUsersByNewsAndClientIds($news, $clientIds);

        $clientIds = collect($clientIds)->merge($oldClientIds)->unique();
        foreach ($clientIds as $clientId) {
            $this->newsNotificationRepository->clearCacheForClient($clientId);
        }

        return true;
    }


    public function deleteAllByNews(News $news): bool
    {
        $this->newsNotificationRepository->deleteAllByNews($news);
        
        $clientIds = $news->newsNotifications->pluck('client_id')->unique();
        foreach ($clientIds as $clientId) {
            $this->newsNotificationRepository->clearCacheForClient($clientId);
        }
        return true;
    }


    public function create(array $data): NewsNotification
    {
        $updated = $this->newsNotificationRepository->create($data);
        return $updated;
    }


    public function update(NewsNotification $news, array $data): NewsNotification
    {
        $updated = $this->newsNotificationRepository->update($news, $data);
        return $updated;
    }


    public function delete(NewsNotification $news): NewsNotification
    {
        $deleted = $this->newsNotificationRepository->delete($news);
        return $deleted;
    }


    public function markAsViewed(NewsNotification $newsNotification): NewsNotification
    {
        $data = ['is_notification_viewed' => true];
        return $this->newsNotificationRepository->update($newsNotification, $data);
    }

}
