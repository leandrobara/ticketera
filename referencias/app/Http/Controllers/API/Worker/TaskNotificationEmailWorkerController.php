<?php

namespace App\Http\Controllers\API\Worker;

use Exception;
use DateTimeZone;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use App\Services\API\Views\TaskService;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\TaskNotificationEmailService;
use App\Services\API\Views\TaskService as ViewsTaskService;


class TaskNotificationEmailWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(500);
    }


    public function dailySend(Request $req, TaskNotificationEmailService $notifService)
    {
        $sentTaskNotifEmailIds = $notifService->sendTasksExpiringTodayNotificationEmailsToClients();
        return $this->getSuccessResponse($sentTaskNotifEmailIds);
    }


    public function dailySendEachExpired(Request $req)
    {
        $taskService = resolve(TaskService::class);
        $clientService = resolve(ClientService::class);
        $taskNotificationEmailService = resolve(TaskNotificationEmailService::class);

        $clients = $clientService->findWithEnabledDailyEmailForEachExpiredTask();
        $clients = $clients->filter(fn (Client $client) => $client->enabled);
        foreach ($clients as $client) {
            $clientCurrentHour = $this->getClientCurrentHour($client);
            if ($clientCurrentHour < 7 || $clientCurrentHour > 12) {
                continue;
            }

            SystemHelper::doFlush();
            $this->printClientInfo($client);

            $users = $client->users->filter(fn (User $user) => $user->enabled);
            foreach ($users as $user) {
                $expiredTasks = $taskService->findExpiredByUser($user);
                if ($expiredTasks->isEmpty()) {
                    continue;
                }
                
                $this->printUserInfo($user);
                try {
                    $expiredTasksFromOtherUsers = $expiredTasks->whereNotIn('user_id', $user->id);
                    if ($expiredTasksFromOtherUsers->isNotEmpty()) {
                        throw new Exception('some_users_does_not_match_with_task_expired_user');
                    }

                    $sentTaskNotifEmailIds = new Collection();
                    foreach ($expiredTasks as $expiredTask) {
                        $taskNotificationEmailIds = $taskNotificationEmailService
                            ->sendTasksExpiredNotificationEmailToUser($user, new Collection([$expiredTask]))
                        ;
                        $sentTaskNotifEmailIds = $sentTaskNotifEmailIds->merge($taskNotificationEmailIds);
                    }
                    var_dump($sentTaskNotifEmailIds);
                } catch (Exception $e) {
                    dump($e);
                    report($e);
                    continue;
                }
            }
        }
    }


    public function dailySendAllExpired(Request $req)
    {
        $taskService = resolve(TaskService::class);
        $clientService = resolve(ClientService::class);
        $taskNotificationEmailService = resolve(TaskNotificationEmailService::class);

        $clients = $clientService->findWithEnabledDailyEmailForAllExpiredTasks();
        $clients = $clients->filter(fn (Client $client) => $client->enabled);
        foreach ($clients as $client) {
            $clientCurrentHour = $this->getClientCurrentHour($client);
            if ($clientCurrentHour < 7 || $clientCurrentHour > 12) {
                continue;
            }

            SystemHelper::doFlush();
            $this->printClientInfo($client);

            $users = $client->users->filter(fn (User $user) => $user->enabled);
            foreach ($users as $user) {
                $expiredTasks = $taskService->findExpiredByUser($user);
                if ($expiredTasks->isEmpty()) {
                    continue;
                }
                
                $this->printUserInfo($user);
                try {
                    $expiredTasksFromOtherUsers = $expiredTasks->whereNotIn('user_id', $user->id);
                    if ($expiredTasksFromOtherUsers->isNotEmpty()) {
                        throw new Exception('some_users_does_not_match_with_task_expired_user');
                    }

                    $sentTaskNotifEmailIds = new Collection();
                    $taskNotificationEmailIds = $taskNotificationEmailService
                        ->sendTasksExpiredNotificationEmailToUser($user, $expiredTasks)
                    ;
                    $sentTaskNotifEmailIds = $sentTaskNotifEmailIds->merge($taskNotificationEmailIds);
                    var_dump($sentTaskNotifEmailIds);
                } catch (Exception $e) {
                    dump($e);
                    report($e);
                    continue;
                }
            }
        }
    }


    public function beforeExpirationSend(Request $req)
    {
        $lockKey = 'TaskNotificationEmailWorkerController::beforeExpirationSend';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 900)) {
            die('Locked');
        }
        resolve(LockHelper::class)->getLockByName($lockKey, 900);

        $minutesToExpire = 16;
        $clientService = resolve(ClientService::class);
        $viewsTaskService = resolve(ViewsTaskService::class);
        $taskNotifService = resolve(TaskNotificationEmailService::class);

        $clients = $clientService->findAllEnabled()->filter(function ($client) {
            return $client->clientSettings->enable_task_hour_reminder_email_alert;
        });

        foreach ($clients as $client) {
            SystemHelper::doFlush();
            resolve(LockHelper::class)->getLockByName($lockKey, 900);
            
            $this->printClientInfo($client);

            $tasksExpiringNow = $viewsTaskService->findTasksExpiringNowByClientAndMinutesToExpire(
                $client, $minutesToExpire
            );
            if ($tasksExpiringNow->isEmpty()) {
                continue;
            }

            $taskNotifEmails = $taskNotifService->sendTasksExpiringNowNotificationEmailToUsers($tasksExpiringNow);
            var_dump('$taskNotificationEmailIds', $taskNotifEmails->pluck('id')->toArray());
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
        // $response = $taskNotifService->sendTasksExpiringNowNotificationEmailsToClients($minutesToExpire);
        // return $this->getSuccessResponse($sentTaskNotifEmailIds);
    }


    private function getClientCurrentHour($client)
    {
        $clientTz = new DateTimeZone($client->timezone);
        $clientCurrentDate = (new Carbon('now'))->setTimezone($clientTz);
        $clientCurrentHour = (int) $clientCurrentDate->format('H');
        return $clientCurrentHour;
    }


    private function printClientInfo(Client $client): void
    {
        echo "<h3>- Client ID {$client->id}: {$client->name} </h3>";
    }


    private function printUserInfo(User $user): void
    {
        echo "<h4>- User ID {$user->id}: {$user->username} </h4>";
    }

}
