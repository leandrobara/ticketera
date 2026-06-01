<?php
namespace App\Services\API;

use DateTime;
use Exception;
use DateTimeZone;
use Carbon\Carbon;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use App\Services\API\Views\TaskService;
use App\Services\Traits\GetUserFromRequest;
use App\Models\TaskNotificationWhatsAppMessage;
use App\Repositories\TaskNotificationWhatsAppMessageRepository;
use App\Services\API\Dispatchers\WhatsAppNotificationEventsDispatcherService;
use App\Repositories\Criteria\Filter\TaskNotificationWhatsAppMessage\DispatchedTodayCriteria;


class TaskNotificationWhatsAppMessageService
{
    use GetUserFromRequest;

    public function __construct(
        private readonly ClientService $clientService,
        private readonly TaskService $viewsTaskService,
        private readonly bool $notificationsWhatsAppMessageEnabled,
        private readonly TaskNotificationWhatsAppMessageRepository $taskNotificationWhatsAppMessageRepository,
        private readonly WhatsAppNotificationEventsDispatcherService $whatsAppNotificationEventsDispatcherService,
    ) {
    }


    public function create(array $data): TaskNotificationWhatsAppMessage
    {
        $taskNotification = $this->taskNotificationWhatsAppMessageRepository->create($data);
        return $taskNotification;
    }


    public function createNewDefault(Task $task): TaskNotificationWhatsAppMessage
    {
        $dateNow = new DateTime('now');
        $data = [
            'type' => 'new_task',
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'client_id' => $task->client->id,
            'send_date' => $dateNow->format('Y-m-d H:i:s'),
        ];
        
        $whatsAppNotificationsAreEnabled = $this->notificationsWhatsAppMessageEnabled;
        $newTaskWhatsAppMsgIsEnabled = $task->client->clientSettings->enable_new_task_whatsapp_message_alert;

        $isTaskAssignedToLoggedUser = true;
        $loggedUser = $this->getRequestUserOrNull();
        if ($loggedUser?->id != $task->user_id) {
            $isTaskAssignedToLoggedUser = false;
        }

        if (!$whatsAppNotificationsAreEnabled || !$newTaskWhatsAppMsgIsEnabled || $isTaskAssignedToLoggedUser) {
            $data['send_date'] = null;
            $data['do_not_send'] = true;
        }

        $taskNotificationWhatsAppMessage = $this->create($data);
        return $taskNotificationWhatsAppMessage;
    }


    public function createTaskUserChangeDefault(Task $task): TaskNotificationWhatsAppMessage
    {
        $data = [
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'type' => 'task_user_change',
            'client_id' => $task->client->id,
            'send_date' => new DateTime('now'),
        ];

        $isTaskAssignedToLoggedUser = true;
        $loggedUser = $this->getRequestUserOrNull();

        if ($loggedUser?->id != $task->user_id) {
            $isTaskAssignedToLoggedUser = false;
        }

        $whatsAppNotificationsAreEnabled = $this->notificationsWhatsAppMessageEnabled;
        $newTaskWhatsAppMsgIsEnabled = $task->client->clientSettings->enable_new_task_whatsapp_message_alert;

        if (!$whatsAppNotificationsAreEnabled || !$newTaskWhatsAppMsgIsEnabled || $isTaskAssignedToLoggedUser) {
            $data['send_date'] = null;
            $data['do_not_send'] = true;
        }

        $taskNotificationWhatsAppMessage = $this->create($data);
        return $taskNotificationWhatsAppMessage;
    }


    public function findByIds(Collection $ids): Collection
    {
        return $this->taskNotificationWhatsAppMessageRepository->findByIds($ids);
    }


    public function findLastUserChangeTypeNotificationByTask(Task $task): ?TaskNotificationWhatsAppMessage
    {
        return $this->taskNotificationWhatsAppMessageRepository->findLastUserChangeTypeNotificationByTask($task);
    }


    public function update(
        TaskNotificationWhatsAppMessage $taskNotificationWhatsAppMessage,
        array $data
    ): TaskNotificationWhatsAppMessage {
        $updated = $this->taskNotificationWhatsAppMessageRepository->update($taskNotificationWhatsAppMessage, $data);
        return $updated;
    }


    public function updateMultiple(Collection $taskNotificationWhatsAppMessages, array $data): Collection
    {
        $updatedNotifs = $this->taskNotificationWhatsAppMessageRepository->updateMultiple(
            $taskNotificationWhatsAppMessages, $data
        );
        return $updatedNotifs;
    }


    public function delete(TaskNotificationWhatsAppMessage $taskNotificationWhatsAppMessage): Note
    {
        $deleted = $this->taskNotificationWhatsAppMessageRepository->delete($taskNotificationWhatsAppMessage);
        return $deleted;
    }


    // public function sendTasksExpiringTodayNotificationWhatsAppMessageToUsers(
    //     Collection $tasksExpiringToday
    // ): Collection {
    //     if ($tasksExpiringToday->isEmpty()) {
    //         return new Collection();
    //     }
    //     if (!$this->notificationsWhatsAppMessageEnabled) {
    //         return new Collection();
    //     }

    //     // Filtro las tareas que ya recibieron la notificación
    //     $tasksExpiringToday = $tasksExpiringToday->filter(function (Task $task) {
    //         return !($task->taskDailyNotificationWhatsAppMessage);
    //     });
    //     if ($tasksExpiringToday->isEmpty()) {
    //         return new Collection();
    //     }

    //     $clientIds = $tasksExpiringToday->pluck('client_id')->unique();
    //     if ($clientIds->isEmpty()) {
    //         throw new Exception('no_client_in_tasks');
    //     }
    //     if ($clientIds->count() > 1) {
    //         throw new Exception('tasks_has_multiple_clients');
    //     }
        
    //     $client = $tasksExpiringToday->first()->client;
    //     if (!$client->clientSettings->enable_daily_task_whatsapp_message_alert) {
    //         throw new Exception('client_has_disabled_daily_task_email_alert');
    //     }

    //     $clientTz = new DateTimeZone($client->timezone);
    //     $clientCurrentDateStr = $this->getClientCurrentDateByClient($client);
    //     $limitDates = $tasksExpiringToday->map(function (Task $task) use ($clientTz) {
    //         return $task->limit_date->setTimezone($clientTz)->format('Y-m-d');
    //     })->unique();
    //     if ($limitDates->count() > 1) {
    //         throw new Exception('tasks_has_multiple_limit_dates');
    //     }
    //     if ($limitDates->first() !== $clientCurrentDateStr) {
    //         throw new Exception('tasks_limit_date_different_from_today');
    //     }

    //     $dateNow = new Datetime('now');
    //     $dispatchedNotifications = new Collection();
    //     foreach ($client->users as $user) {
    //         $tasksExpiringTodayFromUser = $tasksExpiringToday->where('user_id', $user->id);
    //         if ($tasksExpiringTodayFromUser->isEmpty()) {
    //             continue;
    //         }
            
    //         try {
    //             DB::beginTransaction();

    //             $userTaskNotifications = new Collection();
    //             foreach ($tasksExpiringTodayFromUser as $task) {
    //                 $taskNotifData = [
    //                     'type' => 'daily',
    //                     'task_id' => $task->id,
    //                     'user_id' => $user->id,
    //                     'client_id' => $client->id,
    //                     'send_date' => $dateNow->format('Y-m-d H:i:s'),
    //                     'dispatched_date' => $dateNow->format('Y-m-d H:i:s'),
    //                 ];
    //                 $userTaskNotification = $this->create($taskNotifData);
    //                 $userTaskNotifications->push($userTaskNotification);
    //             }

    //             $dispatchedNotifications = $dispatchedNotifications->merge($userTaskNotifications);

    //             DB::commit();
    //         } catch (Exception $e) {
    //             DB::rollBack();
    //             throw $e;
    //         }
    //     }
    //     return $dispatchedNotifications;
    // }


    public function sendTasksNotificationBeforeExpiringWhatsAppMessageToUsers(Collection $tasksExpiringNow)
    {
        $processedTaskNotifications = new Collection();
        if (!$this->notificationsWhatsAppMessageEnabled) {
            return $processedTaskNotifications;
        }

        // Filtro las tareas que ya recibieron la notificación
        $tasksExpiringNow = $tasksExpiringNow->filter(function (Task $task) {
            return $task->taskExpiresNowNotificationWhatsAppMessage ? false : true;
        });
        if ($tasksExpiringNow->isEmpty()) {
            return $processedTaskNotifications;
        }

        $clientIds = $tasksExpiringNow->pluck('client_id')->unique();
        if ($clientIds->isEmpty()) {
            throw new Exception('no_client_in_tasks');
        }
        if ($clientIds->count() > 1) {
            throw new Exception('tasks_has_multiple_clients');
        }

        $client = $tasksExpiringNow->first()->client;
        if (!$client->clientSettings->enable_wapi) {
            throw new Exception('client_has_disabled_wapi');
        }
        if (!$client->clientSettings->enable_task_hour_reminder_whatsapp_message_alert) {
            throw new Exception('client_has_disabled_reminder_whatsapp_message_alert');
        }
        
        $dateNow = new Carbon('now');
        $clientTz = new DateTimeZone($client->timezone);
        $clientCurrentDate = (clone $dateNow)->setTimezone($clientTz);
        $clientCurrentDateStr = $clientCurrentDate->format('Y-m-d');
        $limitDates = $tasksExpiringNow->map(function ($task) use ($clientTz) {
            return $task->limit_date->setTimezone($clientTz)->format('Y-m-d');
        })->unique();

        if ($limitDates->count() > 1) {
            throw new Exception('tasks_has_multiple_limit_dates');
        }
        if ($limitDates->first() !== $clientCurrentDateStr) {
            throw new Exception('tasks_limit_date_different_from_today');
        }

        $delaySecs = 0;
        foreach ($tasksExpiringNow->values() as $i => $task) {
            if (!$task->lead) {
                continue;
            }
            $user = $task->user;
            if (!$user || !$user->enabled || !$user->wapi_is_synced || !$user->wapi_session_phone_number) {
                continue;
            }

            try {
                DB::beginTransaction();

                $taskNotifData = [
                    'type' => 'expires_now',
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'client_id' => $client->id,
                    'send_date' => $dateNow->format('Y-m-d H:i:s'),
                    'dispatched_date' => $dateNow->format('Y-m-d H:i:s'),
                ];
                $taskNotification = $this->create($taskNotifData);
                $processedTaskNotifications->push($taskNotification);

                $nextMsgDelaySecs = $i == 0 ? 40 : 15;
                $delaySecs = $delaySecs + $nextMsgDelaySecs;
                $this->whatsAppNotificationEventsDispatcherService->dispatchSendWAPITaskNotificationBeforeExpiresJob(
                    $user, $taskNotification, $delaySecs
                );
                
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        return $processedTaskNotifications;
    }


    public function sendNewTaskWhatsAppMessageNotificationToTaskUser(
        Task $task,
        User $assignerUser
    ): TaskNotificationWhatsAppMessage {
        $taskNotifWhatsAppMessage = $task->newTaskNotificationWhatsAppMessage;
        if (!$taskNotifWhatsAppMessage) {
            throw new Exception('task_notification_whatsapp_message_does_not_exist');
        }

        if (!$this->notificationsWhatsAppMessageEnabled) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'disabled_notifications_whatsapp_message');
            return $taskNotifWhatsAppMessage->fresh();
        }
        
        if (!$task->user) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'user_was_deleted');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if (!$task->user->enabled) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'user_is_not_enabled');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if (!$task->user->client->clientSettings->enable_new_task_whatsapp_message_alert) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'disabled_new_task_whatsapp_message_alert');
            return $taskNotifWhatsAppMessage->fresh();
        }

        if ($taskNotifWhatsAppMessage->do_not_send) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'task_notification_has_do_not_send_flag');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if ($taskNotifWhatsAppMessage->dispatched_date) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'task_notification_has_dispatched_date');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if ($taskNotifWhatsAppMessage->sent_date) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'task_notification_has_sent_date');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if (!$taskNotifWhatsAppMessage->send_date) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'task_notification_does_not_have_send_date');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if ($taskNotifWhatsAppMessage->success) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'task_notification_has_success_flag');
            return $taskNotifWhatsAppMessage->fresh();
        }

        try {
            DB::beginTransaction();
            
            $dateNow = new DateTime();
            $taskNotifWhatsAppMessage = $this->update($taskNotifWhatsAppMessage, ['dispatched_date' => $dateNow]);
            $this->whatsAppNotificationEventsDispatcherService->dispatchSendWAPINewTaskNotificationMessageJob(
                $taskNotifWhatsAppMessage, $assignerUser
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $taskNotifWhatsAppMessage->fresh();
    }


    public function sendTaskUserChangeWhatsAppMessageToTaskUser(
        Task $task,
        User $oldUser,
        User $assignerUser,
    ): TaskNotificationWhatsAppMessage {
        $taskNotifWhatsAppMessage = $this->findLastUserChangeTypeNotificationByTask($task);
        if (!$taskNotifWhatsAppMessage) {
            throw new Exception('task_notification_whatsapp_message_does_not_exist');
        }

        if (!$this->notificationsWhatsAppMessageEnabled) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'disabled_notifications_whatsapp_message');
            return $taskNotifWhatsAppMessage->fresh();
        }

        if (!$task->user) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'user_was_deleted');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if (!$task->user->enabled) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'user_is_not_enabled');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if (!$task->user->client->clientSettings->enable_task_user_change_whatsapp_message_alert) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'disabled_task_user_change_whatsapp_message');
            return $taskNotifWhatsAppMessage->fresh();
        }
        
        if ($taskNotifWhatsAppMessage->do_not_send) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'task_notification_has_do_not_send_flag');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if ($taskNotifWhatsAppMessage->dispatched_date) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'task_notification_has_dispatched_date');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if ($taskNotifWhatsAppMessage->sent_date) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'task_notification_has_sent_date');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if (!$taskNotifWhatsAppMessage->send_date) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'task_notification_does_not_have_send_date');
            return $taskNotifWhatsAppMessage->fresh();
        }
        if ($taskNotifWhatsAppMessage->success) {
            $this->persistFailReason($taskNotifWhatsAppMessage, 'task_notification_has_success_flag');
            return $taskNotifWhatsAppMessage->fresh();
        }

        try {
            DB::beginTransaction();
            
            $dateNow = new DateTime();
            $taskNotifWhatsAppMessage = $this->update($taskNotifWhatsAppMessage, ['dispatched_date' => $dateNow]);
            $this->whatsAppNotificationEventsDispatcherService->dispatchSendWAPITaskUserChangeNotificationMessageJob(
                oldUser: $oldUser,
                assignerUser: $assignerUser,
                taskNotificationWhatsAppMessage: $taskNotifWhatsAppMessage,
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $taskNotifWhatsAppMessage->fresh();
    }


    public function dailyWhatsAppMessageWasSentTodayToClient(Client $client): bool
    {
        $opts = ['filters' => $this->getFilterCriteriasByName(['type' => 'daily', 'dispatched_today' => $client])];
        $notificationsCount = $this->taskNotificationWhatsAppMessageRepository->countByClient($client, $opts);
        return $notificationsCount > 0;
    }


    private function getClientCurrentDateByClient(Client $client): string
    {
        $dateNow = new Carbon('now');
        $clientTz = new DateTimeZone($client->timezone);
        $clientCurrentDate = (clone $dateNow)->setTimezone($clientTz);
        $clientCurrentDate = $clientCurrentDate->format('Y-m-d');
        return $clientCurrentDate;
    }


    public function persistFailReason(
        Collection | TaskNotificationWhatsAppMessage $taskNotifications,
        string $failReason
    ): Collection {
        if ($taskNotifications instanceof TaskNotificationWhatsAppMessage) {
            $taskNotifications = new Collection([$taskNotifications]);
        }
        $updateData = ['success' => false, 'exception' => $failReason];
        $updatedNotifs = $this->updateMultiple($taskNotifications, $updateData);
        return $updatedNotifs;
    }


    public function persistSuccessSent(Collection | TaskNotificationWhatsAppMessage $taskNotifications): Collection
    {
        if ($taskNotifications instanceof TaskNotificationWhatsAppMessage) {
            $taskNotifications = new Collection([$taskNotifications]);
        }
        $updateData = ['success' => true, 'exception' => null, 'sent_date' => new DateTime()];
        $updatedNotifs = $this->updateMultiple($taskNotifications, $updateData);
        return $updatedNotifs;
    }


    private function getFilterCriteriasByName($filters)
    {
        $criterias = [
            'dispatched_today' => DispatchedTodayCriteria::class,
        ];
        $nfilters = [];
        foreach ($filters as $key => $value) {
            if ($value) {
                if (in_array($key, array_keys($criterias))) {
                    $nfilters[$key] = new $criterias[$key]($value);
                } else {
                    $nfilters[$key] =  $value;
                }
            }
        }
        return $nfilters;
    }

}
