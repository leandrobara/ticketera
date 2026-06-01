<?php

namespace App\Services\API\Views;

use DateTime;
use App\Models\User;
use App\Models\Client;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\Tasks\SortByLimitDate;
use App\Repositories\Criteria\Filter\Tasks\ExpiredCriteria;
use App\Repositories\Criteria\Filter\Tasks\TaskStatusCriteria;
use App\Repositories\Criteria\Filter\Tasks\ExpiresTodayCriteria;
use App\Repositories\Criteria\Filter\Tasks\LimitDateEndCriteria;
use App\Repositories\Criteria\Filter\Tasks\ExpiredByUserCriteria;
use App\Repositories\Criteria\Filter\Tasks\LimitDateStartCriteria;


class TaskService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $taskRepository;


    public function __construct(Repository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }


    public function findPaginatedByFiltersAndClient(array $options, ?Client $client = null): LengthAwarePaginator
    {
        $opts = [
            'page' => $options['page'] ?? 1,
            'with' => $options['with'] ?? [],
            'limit' => $options['limit'] ?? 20,
            'order' => $this->getSortCriteriasByName($options['sort'] ?? ''),
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];

        $search = $options['filters']['search'] ?? null;
        if ($search) {
            unset($opts['filters']['search']);
            $response = $this->taskRepository->searchPaginatedByFiltersAndClient(
                $search, $client ?? $this->getClient(), $opts
            );
            return $response;
        }

        $response = $this->taskRepository->findPaginatedByFiltersAndClient($client ?? $this->getClient(), $opts);
        return $response;
    }


    public function countPending(?Client $client = null, ?User $user = null): int
    {
        $user = $user ?? $this->getUser();
        $client = $client ?? $this->getClient();

        $userIsAdmin = $user && $user->type == 'admin';
        $permissionsRestricted = $client->clientSettings->enable_users_type_permissions_restrictions;
        if (!$userIsAdmin && $permissionsRestricted) {
            return $this->taskRepository->countPendingByUser($user);
        }

        return $this->taskRepository->countPendingByClient($client);
    }


    public function findTasksToNotify(?User $user = null, array $opts = []): Collection
    {
        $client = $user->client ?? $this->getClient();
        
        $userIsAdmin = $user && $user->type == 'admin';
        $permissionsRestricted = $client->clientSettings->enable_users_type_permissions_restrictions;
        if ($user && !$userIsAdmin && $permissionsRestricted) {
            $opts['filters']['user_id'] = $user->id;
        }
        
        $expiredTasks = $this->findTasksExpired($client, $opts);
        $expiringTodayTasks = $this->findTasksExpiringTodayByClient($client, $opts);
        $allTasks = $expiredTasks->merge($expiringTodayTasks);
        return $allTasks;
    }


    public function findTasksExpired(?Client $client = null, array $options = []): Collection
    {
        $client = $client ?? $this->getClient();
        $options['filters'] = array_merge($options['filters'] ?? [], ['expired' => $client]);
        $tasks = $this->findByFiltersAndClient($options, $client);
        return $tasks;
    }


    public function findTasksExpiringTodayByClient(?Client $client = null, array $options = []): Collection
    {
        $client = $client ?? $this->getClient();
        $options['filters'] = array_merge($options['filters'] ?? [], ['expires_today' => $client]);
        $tasks = $this->findByFiltersAndClient($options, $client);
        return $tasks;
    }


    public function findExpiredByUser(User $user, array $opts = []): Collection
    {
        $opts['filters'] = array_merge($opts['filters'] ?? [], ['expired' => $user->client]);
        $tasks = $this->findByFiltersAndUser($user, $opts);
        return $tasks;
    }


    public function findByFiltersAndUser(User $user, array $opts): Collection
    {
        $repoOpts = [
            'limit' => $opts['limit'] ?? 9999999,
            'sort' => $opts['sort'] ?? 'limit_date desc',
            'filters' => $this->getFilterCriteriasByName($opts['filters'] ?? []),
        ];
        return $this->taskRepository->findByFiltersAndUser($user, $repoOpts);
    }


    public function findByFiltersAndClient(array $options, ?Client $client = null): Collection
    {
        $opts = [
            'limit' => $options['limit'] ?? 9999999,
            'sort' => $options['sort'] ?? 'limit_date desc',
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];
        return $this->taskRepository->findByFiltersAndClient($client ?? $this->getClient(), $opts);
    }


    private function getFilterCriteriasByName($filters)
    {
        $criterias = [
            'expired' => ExpiredCriteria::class,
            'status' => TaskStatusCriteria::class,
            'expires_today' => ExpiresTodayCriteria::class,
            'limit_date_end' => LimitDateEndCriteria::class,
            'limit_date_start' => LimitDateStartCriteria::class,
        ];
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
            'limit_date_asc' => new SortByLimitDate('asc'),
            'limit_date_desc' => new SortByLimitDate('desc'),
        ];
        return $sortsName ? $sortTypes[$sortsName] : $sortsName;
    }


    public function findTasksExpiringNowByClientAndMinutesToExpire(
        ?Client $client = null,
        int $minutesToExpire = 15
    ): Collection {
        $client = $client ?? $this->getClient();
        $dateNow = new Datetime('now');
        $dateNowStr = $dateNow->format('Y-m-d H:i:s');
        $limitExpirationDate = $dateNow->modify("+ {$minutesToExpire} minutes");
        $limitExpirationDateStr = $limitExpirationDate->format('Y-m-d H:i:s');
        $options['filters'] = [
            'status' => 'pending',
            'limit_date_start' => $dateNowStr,
            'limit_date_end' => $limitExpirationDateStr,
        ];
        
        $tasks = $this->findByFiltersAndClient($options, $client);
        return $tasks;
    }


    public function listForZapierAppPolling(string $triggerType): Collection
    {
        $client = $this->getClient();
        $options = ['page' => 1, 'limit' => 5, 'with' => [ 'user', 'lead', ]];
        $response = $this->taskRepository->findPaginatedByFiltersAndClient($client, $options);
        $response = $response->getCollection();
        return $response;
    }


    public function listIds(array $options): array
    {
        $options['filters'] = $this->getFilterCriteriasByName($options['filters'] ?? []);
        $ids = $this->taskRepository->listIds($this->getClient(), $options);
        return $ids;
    }

}
