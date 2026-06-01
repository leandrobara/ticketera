<?php

namespace App\Services\API;

use DateTime;
use Exception;
use DateTimeZone;
use Carbon\Carbon;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Helpers\SimpleEncrypter;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use Illuminate\Support\Facades\Lang;
use App\Models\TaskNotificationEmail;
use App\Helpers\ClientyMailerAPIHelper;
use App\Services\Traits\GetUserFromRequest;
use App\Repositories\TaskNotificationEmailRepository;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;
use App\Services\API\Views\TaskService as ViewsTaskService;
use App\Repositories\Criteria\Filter\TaskNotificationEmail\ScheduledTodayCriteria;


class TaskNotificationEmailService
{

    use GetUserFromRequest;

    private $clientService;
    private $viewsTaskService;
    private $notificationFromEmail;
    private $clientyMailerAPIHelper;
    private $notificationsEmailEnabled;
    private $taskNotificationEmailRepository;


    public function __construct(
        TaskNotificationEmailRepository $taskNotificationEmailRepository,
        ViewsTaskService $viewsTaskService,
        ClientService $clientService,
        ClientyMailerAPIHelper $clientyMailerAPIHelper,
        bool $notificationsEmailEnabled,
        string $notificationFromEmail
    ) {
        $this->clientService = $clientService;
        $this->viewsTaskService = $viewsTaskService;
        $this->notificationFromEmail = $notificationFromEmail;
        $this->clientyMailerAPIHelper = $clientyMailerAPIHelper;
        $this->notificationsEmailEnabled = $notificationsEmailEnabled;
        $this->taskNotificationEmailRepository = $taskNotificationEmailRepository;
    }


    public function findLastUserChangeTypeNotificationByTask(Task $task): ?TaskNotificationEmail
    {
        return $this->taskNotificationEmailRepository->findLastUserChangeTypeNotificationByTask($task);
    }


    public function markAsSent(
        TaskNotificationEmail $notif,
        DateTime $sentDate,
        ?int $externalEmailId = null
    ): TaskNotificationEmail {
        $data = ['sent_date' => $sentDate, 'external_email_id' => $externalEmailId];
        $notif = $this->update($notif, $data);
        return $notif;
    }


    public function markMultipleAsSent(
        Collection $notifications,
        DateTime $sentDate,
        ?int $externalEmailId = null
    ): Collection {
        $data = [
            'sent_date' => $sentDate,
            'external_email_id' => $externalEmailId,
        ];
        $updatedNotifs = $this->updateMultiple($notifications, $data);
        return $updatedNotifs;
    }


    public function create(array $data): TaskNotificationEmail
    {
        $taskNotificationEmail = $this->taskNotificationEmailRepository->create($data);
        return $taskNotificationEmail;
    }


    public function createNewDefault(Task $task): TaskNotificationEmail
    {
        $data = [
            'type' => 'new_task',
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'client_id' => $task->client->id,
            'send_date' => new DateTime('now'),
        ];

        $newTaskEmailIsEnabled = $task->client->clientSettings->enable_new_task_email_alert;
        
        $isTaskAssignedToLoggedUser = true;
        $loggedUser = $this->getRequestUserOrNull();
        if ($loggedUser?->id != $task->user_id) {
            $isTaskAssignedToLoggedUser = false;
        }

        if (!$this->notificationsEmailEnabled || !$newTaskEmailIsEnabled || $isTaskAssignedToLoggedUser) {
            $data['send_date'] = null;
            $data['do_not_send'] = true;
        }

        $taskNotificationEmail = $this->create($data);
        return $taskNotificationEmail;
    }


    public function createTaskUserChangeDefault(Task $task): TaskNotificationEmail
    {
        $data = [
            'task_id' => $task->id,
            'type' => 'task_user_change',
            'user_id' => $task->user_id,
            'client_id' => $task->client->id,
            'send_date' => new DateTime('now'),
        ];

        $enableTaskUserChangeEmailAlert = $task->client->clientSettings->enable_task_user_change_email_alert;
        $notificationEmailDisabled = !$this->notificationsEmailEnabled || !$enableTaskUserChangeEmailAlert;

        $loggedUser = $this->getUser();
        $isTaskAssignedToLoggedUser = ($loggedUser->id == $task->user->id);

        if ($notificationEmailDisabled || $isTaskAssignedToLoggedUser) {
            $data['send_date'] = null;
            $data['do_not_send'] = true;
        }

        $notif = $this->create($data);
        return $notif;
    }


    public function createMassiveTaskUserChange(
        User $loggedUser,
        Collection $taskIds
    ): TaskNotificationEmail {
        $data = [
            'task_id' => null,
            'user_id' => $loggedUser->id,
            'send_date' => new DateTime('now'),
            'type' => 'task_massive_user_change',
            'client_id' => $loggedUser->client_id,
            'massive_user_change_task_ids' => $taskIds,
        ];

        $enableTaskUserChangeEmailAlert = $loggedUser->client->clientSettings->enable_task_user_change_email_alert;
        $notificationEmailDisabled = !$this->notificationsEmailEnabled || !$enableTaskUserChangeEmailAlert;

        if ($notificationEmailDisabled) {
            $data['send_date'] = null;
            $data['do_not_send'] = true;
        }

        $tasksNotificationEmail = $this->create($data);
        return $tasksNotificationEmail;
    }


    public function update(TaskNotificationEmail $taskNotificationEmail, array $data): TaskNotificationEmail
    {
        $updated = $this->taskNotificationEmailRepository->update($taskNotificationEmail, $data);
        return $updated;
    }


    public function updateMultiple(Collection $taskNotificationEmails, array $data): Collection
    {
        $updated = $this->taskNotificationEmailRepository->updateMultiple($taskNotificationEmails, $data);
        return $updated;
    }


    public function delete(TaskNotificationEmail $taskNotificationEmail): Note
    {
        $deleted = $this->taskNotificationEmailRepository->delete($taskNotificationEmail);
        return $deleted;
    }


    public function sendNewTaskNotificationEmailToUser(Task $task, User $assignerUser): ?TaskNotificationEmail
    {
        if (!$this->notificationsEmailEnabled) {
            return null;
        }
        $taskNotificationEmail = $task->newTaskNotificationEmail;
        if (!$taskNotificationEmail || $taskNotificationEmail->do_not_send) {
            return null;
        }
        
        $dateNow = new DateTime('now');
        $encryptedLeadId = SimpleEncrypter::encryptInt($task->lead->id);
        $taskUrl = clientUrl($task->client, '/tasks');
        $leadUrl = clientUrl($task->client, "/?eli={$encryptedLeadId}");

        $body = view('api.emails.task.new-task', [
            'task' => $task,
            'leadUrl' => $leadUrl,
            'taskUrl' => $taskUrl,
            'user' => $task->user,
            'lead' => $task->lead,
            'assignerUser' => $assignerUser,
        ])->render();
        $data = [
            'body' => $body,
            'hasOpenTracking' => true,
            'fromName' => 'Clienty CRM',
            'to' => [$task->user->email],
            'from' => $this->notificationFromEmail,
            'sendDate' => $dateNow->format('Y-m-d\TH:i:sP'),
            'appCustomId' => 'SYSTEM_new_task_' . $task->id,
            'appCustomMetadata' => json_encode(['task' => ['id' => $task->id]]),
            'subject' => "{$task->client->name} :: Te han asignado una nueva tarea - ID: {$task->id}",
        ];
        if (redirectEmails()) {
            $data['to'] = [config('emails.redirect_emails_to')];
        }

        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
        $mailerResponseDTO = $this->clientyMailerAPIHelper->scheduleQuickEmail($dto);

        $updatedTaskNotificationEmail = $this->update($taskNotificationEmail, [
            'external_email_id' => $mailerResponseDTO->id,
            'scheduled_date' => $dateNow->format('Y-m-d H:i:s'),
        ]);
        return $updatedTaskNotificationEmail;
    }


    public function sendTaskUserChangeNotificationEmailToTaskUser(
        Task $task,
        User $oldUser,
        User $assignerUser,
    ): ?TaskNotificationEmail {
        if (!$this->notificationsEmailEnabled) {
            return null;
        }

        $taskNotificationEmail = $this->findLastUserChangeTypeNotificationByTask($task);
        if (
            !$taskNotificationEmail ||
            $taskNotificationEmail->do_not_send ||
            $taskNotificationEmail->type != 'task_user_change'
        ) {
            return null;
        }

        $dateNow = new DateTime('now');
        $encryptedLeadId = SimpleEncrypter::encryptInt($task->lead->id);
        $leadUrl = clientUrl($task->client, "/?eli={$encryptedLeadId}");
        $taskUrl = clientUrl($task->client, '/tasks');

        $body = view('api.emails.task.task-user-change', [
            'task' => $task,
            'user' => $task->user,
            'oldUser' => $oldUser,
            'lead' => $task->lead,
            'leadUrl' => $leadUrl,
            'taskUrl' => $taskUrl,
            'assignerUser' => $assignerUser,
        ])->render();

        $data = [
            'body' => $body,
            'subject' => "{$task->client->name} :: Nueva asignación de tarea - ID: {$task->id}",
            'hasOpenTracking' => true,
            'fromName' => 'Clienty CRM',
            'to' => [$task->user->email],
            'from' => $this->notificationFromEmail,
            'appCustomId' => 'SYSTEM_task_user_change_' . $task->id,
            'sendDate' => $dateNow->format('Y-m-d\TH:i:sP'),
            'appCustomMetadata' => json_encode([
                'task' => ['id' => $task->id],
                'old_user' => ['id' => $oldUser->id],
                'taskNotificationEmail' => ['id' => $taskNotificationEmail->id],
            ]),
        ];
        if (redirectEmails()) {
            $data['to'] = [config('emails.redirect_emails_to')];
        }

        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
        $mailerResponseDTO = $this->clientyMailerAPIHelper->scheduleQuickEmail($dto);

        $updatedTaskNotificationEmail = $this->update($taskNotificationEmail, [
            'external_email_id' => $mailerResponseDTO->id,
            'scheduled_date' => $dateNow->format('Y-m-d H:i:s'),
        ]);
        return $updatedTaskNotificationEmail;
    }


    public function sendMassiveTaskUserChangeNotificationEmailToAssignedUser(
        TaskNotificationEmail $taskNotificationEmail,
        User $assignedUser
    ): ?TaskNotificationEmail {
        if (
            !$this->notificationsEmailEnabled ||
            $taskNotificationEmail->do_not_send ||
            !$taskNotificationEmail->massive_user_change_task_ids ||
            $taskNotificationEmail->type !== 'task_massive_user_change'
        ) {
            return null;
        }
    
        $dateNow = new DateTime('now');
        
        $client = $taskNotificationEmail->client;
        $taskUrl = clientUrl($client, '/tasks');

        $assignerUser = $taskNotificationEmail->user;
        $taskIds = $taskNotificationEmail->massive_user_change_task_ids;
        $taskCount = count($taskIds);
    
        $body = view('api.emails.task.task-massive-user-change', [
            'taskUrl' => $taskUrl,
            'taskCount' => $taskCount,
            'assignerUser' => $assignerUser,
            'assignedUser' => $assignedUser,
        ])->render();

        $data = [
            'body' => $body,
            'hasOpenTracking' => true,
            'fromName' => 'Clienty CRM',
            'to' => [$assignedUser->email],
            'from' => $this->notificationFromEmail,
            'sendDate' => $dateNow->format(DATE_ATOM),
            'subject' => "{$client->name} :: Asignación masiva de tareas",
            'appCustomId' => 'SYSTEM_task_massive_user_change_count_' . $taskCount,
            'appCustomMetadata' => json_encode([
                'task_ids' => ['id' => $taskIds],
                'new_user' => ['id' => $assignedUser->id],
                'taskNotificationEmail' => ['id' => $taskNotificationEmail->id],
            ]),
        ];

        if (redirectEmails()) {
            $data['to'] = [config('emails.redirect_emails_to')];
        }
    
        // Envío del correo
        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
        $mailerResponseDTO = $this->clientyMailerAPIHelper->scheduleQuickEmail($dto);
    
        // Actualización del TaskNotificationEmail con el ID externo y fecha
        return $this->update($taskNotificationEmail, [
            'external_email_id' => $mailerResponseDTO->id,
            'scheduled_date' => $dateNow->format('Y-m-d H:i:s'),
        ]);
    }


    public function dailyEmailWasSentTodayToClient(Client $client): bool
    {
        $opts = ['filters' => $this->getFilterCriteriasByName(['type' => 'daily', 'scheduled_today' => $client])];
        $notificationsCount = $this->taskNotificationEmailRepository->countByClient($client, $opts);
        return $notificationsCount > 0;
    }


    public function findExpiredTypeSentTodayByUserAndTasks(User $user, Collection $tasks): Collection
    {
        $opts = [
            'filters' => $this->getFilterCriteriasByName([
                'type' => 'expired',
                'scheduled_today' => $user->client,
                'task_id' => $tasks->pluck('id')->toArray(),
            ])
        ];
        $taskNotificationEmails = $this->taskNotificationEmailRepository->findByUser($user, $opts);
        return $taskNotificationEmails;
    }
    

    //
    // Deprecado
    //
    // public function sendTasksExpiringNowNotificationEmailsToClients(int $minutesToExpire): Collection
    // {
    //     $sentTaskNotifEmailIds = new Collection();
    //     $clients = $this->clientService->findAllEnabled()->filter(function ($client) {
    //         return $client->clientSettings->enable_task_hour_reminder_email_alert;
    //     });

    //     foreach ($clients as $client) {
    //         $tasksExpiringNow = $this->viewsTaskService->findTasksExpiringNowByClientAndMinutesToExpire(
    //             $client, $minutesToExpire
    //         );
    //         if ($tasksExpiringNow->isEmpty()) {
    //             continue;
    //         }

    //         try {
    //             $sentNotifs = $this->sendTasksExpiringNowNotificationEmailToUsers($tasksExpiringNow);
    //         } catch (Exception $e) {
    //             // report($e);
    //             // continue;
    //         }

    //         if ($sentNotifs->isNotEmpty()) {
    //             $sentTaskNotifEmailIds = $sentTaskNotifEmailIds->merge($sentNotifs->pluck('id'));
    //         }
    //     }
    //     return $sentTaskNotifEmailIds;
    // }


    public function sendTasksExpiredNotificationEmailToUser(User $user, Collection $expiredTasks): Collection
    {
        if (!$this->notificationsEmailEnabled) {
            return new Collection();
        }
        // Filtro las tareas que ya fueron informadas hoy
        $sentTodayTaskNotifIds = $this->findExpiredTypeSentTodayByUserAndTasks($user, $expiredTasks)->pluck('task_id');
        $expiredTasks = $expiredTasks->filter(function (Task $task) use ($sentTodayTaskNotifIds) {
            return !$sentTodayTaskNotifIds->contains($task->id);
        });
        if ($expiredTasks->isEmpty()) {
            return new Collection();
        }

        $dateNow = new Carbon('now');
        $isOnlyOneTask = $expiredTasks->count() == 1;
        $tasksUrl = clientUrl($user->client, '/tasks');
        $expiredTasks = $expiredTasks->map(function (Task $task) use ($user) {
            $encryptedLeadId = SimpleEncrypter::encryptInt($task->lead_id);
            $task->directLeadUrl = clientUrl($user->client, "/?eli={$encryptedLeadId}");
            return $task;
        });
        
        $viewData = [
            'user' => $user,
            'tasksUrl' => $tasksUrl,
            'expiredTasks' => $expiredTasks->take(99), // Máximo de 99 tareas vencidas se envían.
        ];

        $body = view('api.emails.task-notification.tasks-expired', $viewData)->render();
        $subject = $isOnlyOneTask
            ? "Clienty CRM | La tarea \"{$expiredTasks->first()->title}\" está vencida"
            : "Clienty CRM | Tienes {$expiredTasks->count()} tareas que están vencidas"
        ;
        $data = [
            'body' => $body,
            'subject' => $subject,
            'to' => [$user->email],
            'fromName' => 'Clienty',
            'hasOpenTracking' => true,
            'from' => $this->notificationFromEmail,
            'sendDate' => $dateNow->format('Y-m-d\TH:i:sP'),
            'appCustomId' => "SYSTEM_CID_{$user->client_id}_UID_{$user->id}_expired_tasks",
            'appCustomMetadata' => json_encode([
                'user' => ['id' => $user->id],
                'client' => ['id' => $user->client_id],
                'tasks' => $expiredTasks->map(fn ($task) => ['id' => $task->id]),
            ]),
        ];
        if (redirectEmails()) {
            $data['to'] = [config('emails.redirect_emails_to')];
        }
        
        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
        $mailerResponseDTO = $this->clientyMailerAPIHelper->scheduleQuickEmail($dto);
        $commonData = [
            'type' => 'expired',
            'user_id' => $user->id,
            'client_id' => $user->client_id,
            'external_email_id' => $mailerResponseDTO->id,
            'scheduled_date' => $dateNow->format('Y-m-d H:i:s'),
        ];

        $taskNotificationEmailIds = new Collection();
        foreach ($expiredTasks as $expiredTask) {
            $taskNotificationEmail = $this->create($commonData + ['task_id' => $expiredTask->id]);
            $taskNotificationEmailIds->push($taskNotificationEmail->id);
        }
        return $taskNotificationEmailIds;
    }


    public function sendTasksExpiringTodayNotificationEmailsToClients(): Collection
    {
        $sentTaskNotifEmailIds = new Collection();
        $clients = $this->clientService->findAllEnabled()->filter(function ($client) {
            return $client->clientSettings->enable_daily_task_email_alert;
        });

        foreach ($clients as $client) {
            $dailyEmailWasSent = $this->dailyEmailWasSentTodayToClient($client);
            if ($dailyEmailWasSent) {
                continue;
            }

            $tasksExpiringToday = $this->viewsTaskService->findTasksExpiringTodayByClient($client);
            if ($tasksExpiringToday->isEmpty()) {
                continue;
            }

            $clientTz = new DateTimeZone($client->timezone);
            $clientCurrentDate = (new Carbon('now'))->setTimezone($clientTz);
            $clientCurrentHour = (int) $clientCurrentDate->format('H');
            if ($clientCurrentHour < 8 || $clientCurrentHour > 12) {
                continue;
            }

            try {
                $sentNotifs = $this->sendTasksExpiringTodayNotificationEmailToUsers($tasksExpiringToday);
            } catch (Exception $e) {
                report($e);
                continue;
            }

            if ($sentNotifs->isNotEmpty()) {
                $sentTaskNotifEmailIds = $sentTaskNotifEmailIds->merge($sentNotifs->pluck('id'));
            }
        }
        return $sentTaskNotifEmailIds;
    }


    public function sendTasksExpiringTodayNotificationEmailToUsers(Collection $tasksExpiringToday): Collection
    {
        $processedTaskNotificationEmails = new Collection();
        if (!$this->notificationsEmailEnabled) {
            return $processedTaskNotificationEmails;
        }

        // Prevention filter
        $tasksExpiringToday = $tasksExpiringToday->filter(function ($task) {
            $taskDailyNotificationEmail = $task->taskDailyNotificationEmail;
            if (!$taskDailyNotificationEmail) {
                return true;
            }
            $notifAlreadySent = $taskDailyNotificationEmail->sent_date || $taskDailyNotificationEmail->scheduled_date;
            return !$notifAlreadySent;
        });

        if ($tasksExpiringToday->isEmpty()) {
            return $processedTaskNotificationEmails;
        }

        $clientIds = $tasksExpiringToday->pluck('client_id')->unique();
        if ($clientIds->isEmpty()) {
            throw new Exception('no_client_in_tasks');
        }
        if ($clientIds->count() > 1) {
            throw new Exception('tasks_has_multiple_clients');
        }
        $client = $tasksExpiringToday->first()->client;
        // if (!$client->emails) {
        //     throw new Exception('client_has_no_emails');
        // }
        if (!$client->clientSettings->enable_daily_task_email_alert) {
            throw new Exception('client_has_disabled_daily_task_email_alert');
        }

        $dateNow = new Carbon('now');
        $clientTz = new DateTimeZone($client->timezone);
        $clientCurrentDate = (clone $dateNow)->setTimezone($clientTz);
        $clientCurrentDateStr = $clientCurrentDate->format('Y-m-d');
        $limitDates = $tasksExpiringToday->map(function ($task) use ($clientTz) {
            return $task->limit_date->setTimezone($clientTz)->format('Y-m-d');
        })->unique();
        if ($limitDates->count() > 1) {
            throw new Exception('tasks_has_multiple_limit_dates');
        }
        if ($limitDates->first() !== $clientCurrentDateStr) {
            throw new Exception('tasks_limit_date_different_from_today');
        }

        $dayOfMonth = $clientCurrentDate->format('j');
        $dayName = Lang::get('datetime.weekDay.' . $clientCurrentDate->format('l'), [], 'es');
        $monthName = Lang::get('datetime.month.' . $clientCurrentDate->format('F'), [], 'es');
        $subject = "Clienty CRM | Tareas del día $dayName $dayOfMonth de $monthName";

        foreach ($client->users as $user) {
            $tasksExpiringTodayFromUser = $tasksExpiringToday->where('user_id', $user->id);
            if ($tasksExpiringTodayFromUser->isEmpty()) {
                continue;
            }

            $tasksExpiringTodayFromUser = $tasksExpiringTodayFromUser->map(function (Task $task) use ($user) {
                $encryptedLeadId = SimpleEncrypter::encryptInt($task->lead_id);
                $task->directLeadUrl = clientUrl($user->client, "/?eli={$encryptedLeadId}");
                return $task;
            });

            $viewData = ['user' => $user, 'tasksExpiringToday' => $tasksExpiringTodayFromUser];
            $body = view('api.emails.task-notification.expiring-today', $viewData)->render();
            $taskIds = $tasksExpiringTodayFromUser->pluck('id');

            $data = [
                'body' => $body,
                'subject' => $subject,
                'to' => [$user->email],
                'fromName' => 'Clienty CRM',
                'hasOpenTracking' => true,
                'from' => $this->notificationFromEmail,
                'sendDate' => $dateNow->format('Y-m-d\TH:i:sP'),
                'appCustomId' => "SYSTEM_CID_{$client->id}_UID_{$user->id}_expiring_today_tasks",
                'appCustomMetadata' => json_encode([
                    'task' => ['id' => $taskIds],
                    'user' => ['id' => $user->id],
                    'client' => ['id' => $client->id],
                ]),
            ];
            if (redirectEmails()) {
                $data['to'] = [config('emails.redirect_emails_to')];
            }

            $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
            $mailerResponseDTO = $this->clientyMailerAPIHelper->scheduleQuickEmail($dto);
            $externalEmailId = $mailerResponseDTO->id;
            $scheduledDate = $dateNow->format('Y-m-d H:i:s');

            $commonData = [
                'type' => 'daily',
                'user_id' => $user->id,
                'client_id' => $client->id,
                'scheduled_date' => $scheduledDate,
                'external_email_id' => $externalEmailId,
            ];

            foreach ($tasksExpiringTodayFromUser as $task) {
                $taskNotificationEmail = $this->create($commonData + ['task_id' => $task->id]);
                $processedTaskNotificationEmails->push($taskNotificationEmail);
            }
        }

        return $processedTaskNotificationEmails;
    }


    public function sendTasksExpiringNowNotificationEmailToUsers(Collection $tasksExpiringNow): Collection
    {
        $processedTaskNotificationEmails = new Collection();
        if (!$this->notificationsEmailEnabled) {
            return $processedTaskNotificationEmails;
        }

        // Prevention filter
        $tasksExpiringNow = $tasksExpiringNow->filter(function ($task) {
            $taskExpiresNowNotifEmail = $task->taskExpiresNowNotificationEmail;
            if (!$taskExpiresNowNotifEmail) {
                return true;
            }
            $notificationWasSent = $taskExpiresNowNotifEmail->sent_date || $taskExpiresNowNotifEmail->scheduled_date;
            return !$notificationWasSent;
        });

        if ($tasksExpiringNow->isEmpty()) {
            return $processedTaskNotificationEmails;
        }

        $clientIds = $tasksExpiringNow->pluck('client_id')->unique();
        if ($clientIds->isEmpty()) {
            throw new Exception('no_client_in_tasks');
        }
        if ($clientIds->count() > 1) {
            throw new Exception('tasks_has_multiple_clients');
        }
        $client = $tasksExpiringNow->first()->client;
        if (!$client->clientSettings->enable_task_hour_reminder_email_alert) {
            throw new Exception('enable_task_hour_reminder_email_alert');
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
        foreach ($tasksExpiringNow as $taskExpiringNow) {
            $user = $taskExpiringNow->user;
            $encryptedLeadId = SimpleEncrypter::encryptInt($taskExpiringNow->lead_id);
            $taskExpiringNow->directLeadUrl = clientUrl($client, "/?eli={$encryptedLeadId}");

            $viewData = ['user' => $user, 'taskExpiringNow' => $taskExpiringNow];
            $body = view('api.emails.task-notification.expiring-now', $viewData)->render();

            $data = [
                'body' => $body,
                'to' => [$user->email],
                'fromName' => 'Clienty CRM',
                'hasOpenTracking' => true,
                'from' => $this->notificationFromEmail,
                'sendDate' => $dateNow->format('Y-m-d\TH:i:sP'),
                'subject' => 'Clienty CRM | Tarea a punto de vencer',
                'appCustomId' => "SYSTEM_CID_{$client->id}_UID_{$user->id}_expiring_now_task",
                'appCustomMetadata' => json_encode([
                    'user' => ['id' => $user->id],
                    'client' => ['id' => $client->id],
                    'task' => ['id' => $taskExpiringNow->id],
                ]),
            ];
            if (redirectEmails()) {
                $data['to'] = [config('emails.redirect_emails_to')];
            }

            $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
            $mailerResponseDTO = $this->clientyMailerAPIHelper->scheduleQuickEmail($dto);
            $externalEmailId = $mailerResponseDTO->id;
            $scheduledDate = $dateNow->format('Y-m-d H:i:s');

            $commonData = [
                'user_id' => $user->id,
                'type' => 'expires_now',
                'client_id' => $client->id,
                'scheduled_date' => $scheduledDate,
                'external_email_id' => $externalEmailId,
            ];
            
            $taskNotificationEmail = $this->create($commonData + ['task_id' => $taskExpiringNow->id]);
            $processedTaskNotificationEmails->push($taskNotificationEmail);
        }
        return $processedTaskNotificationEmails;
    }


    private function getFilterCriteriasByName($filters)
    {
        $criterias = [
            'scheduled_today' => ScheduledTodayCriteria::class,
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
