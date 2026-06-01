<?php

namespace App\Repositories;

use DateTime;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Models\TaskNotificationWhatsAppMessage;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class TaskNotificationWhatsAppMessageRepository
{

    public function create(array $data): TaskNotificationWhatsAppMessage
    {
        $notif = new TaskNotificationWhatsAppMessage($data);
        $notif->saveOrFail();
        return $notif->fresh();
    }


    public function findByClient(Client $client, array $options = []): Collection
    {
        $queryBuilder = $this->buildFindQueryBuilder($options, $client);
        return $queryBuilder->get();
    }


    public function findByIds(Collection $ids): Collection
    {
        return TaskNotificationWhatsAppMessage::whereIn('id', $ids)->get();
    }
    

    public function findLastUserChangeTypeNotificationByTask(Task $task): ?TaskNotificationWhatsAppMessage
    {
        return TaskNotificationWhatsAppMessage::where('type', 'task_user_change')
            ->where('task_id', $task->id)
            ->orderBy('id', 'desc')
            ->first()
        ;
    }
    

    public function findByUser(User $user, array $options = []): Collection
    {
        $filters = $options['filters'] ?? [];
        $queryBuilder = TaskNotificationWhatsAppMessage::where('user_id', $user->id);

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
        return $queryBuilder->get();
    }


    public function countByClient(Client $client, array $options = []): int
    {
        $queryBuilder = $this->buildFindQueryBuilder($options, $client);
        // DB::enableQueryLog();
        return $queryBuilder->count();
        // dd(DB::getQueryLog());
    }


    public function update(TaskNotificationWhatsAppMessage $notif, array $data): TaskNotificationWhatsAppMessage
    {
        $notif->fill($data);
        $notif->saveOrFail();
        return $notif->fresh();
    }


    public function updateMultiple(Collection $taskNotificationEmails, array $data): Collection
    {
        $ids = $taskNotificationEmails->pluck('id');
        $updated = TaskNotificationWhatsAppMessage::whereIn('id', $ids)->update($data);
        $updatedNotifs = TaskNotificationWhatsAppMessage::whereIn('id', $ids)->get();
        return $updatedNotifs;
    }


    public function delete(TaskNotificationWhatsAppMessage $notif): TaskNotificationWhatsAppMessage
    {
        $notif->delete();
        return $notif->fresh();
    }


    protected function buildFindQueryBuilder(array $options = [], ?Client $client = null): Builder
    {
        $order = $options['sort'] ?? [];
        $filters = $options['filters'] ?? [];

        $queryBuilder = TaskNotificationWhatsAppMessage::query();
        if ($client) {
            $queryBuilder->where('client_id', $client->id);
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
            $queryBuilder->orderBy($order, 'DESC');
        }
        return $queryBuilder;
    }

}
