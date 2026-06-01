<?php

namespace App\Http\Controllers\API\Worker;

use DateTime;
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
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Views\TaskService as ViewsTaskService;
use App\Services\API\TaskNotificationWhatsAppMessageService;

use App\Services\API\WAPIService;



class TaskNotificationWhatsAppWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(500);
    }


    public function beforeExpirationSend(Request $req)
    {
        $lockKey = 'TaskNotificationWhatsAppWorkerController::beforeExpirationSend';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 300)) {
            die('Locked');
        }
        if (!config('wapi.wapi_notifications_enabled')) {
            die('wapi_notifications_is_not_enabled');
        }
        
        $minutesToExpire = 15;
        $clientService = resolve(ClientService::class);
        $viewsTaskService = resolve(ViewsTaskService::class);
        $notifsService = resolve(TaskNotificationWhatsAppMessageService::class);

        $clients = $clientService->findWithEnabledTaskHourReminderWhatsAppMessageAlert()->filter(function ($client) {
            return $client->clientSettings->enable_wapi;
        });
        foreach ($clients as $client) {
            SystemHelper::doFlush();
            resolve(LockHelper::class)->getLockByName($lockKey, 300);
            
            $this->printSeparator();
            $this->printClientInfo($client);

            $tasksExpiringNow = $viewsTaskService->findTasksExpiringNowByClientAndMinutesToExpire(
                $client, $minutesToExpire
            );
            if ($tasksExpiringNow->isEmpty()) {
                continue;
            }

            $dispatchedNotifs = $notifsService->sendTasksNotificationBeforeExpiringWhatsAppMessageToUsers(
                $tasksExpiringNow
            );

            $this->printDispatchedNotificationsInfo($dispatchedNotifs);
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function dailySend(Request $req)
    {
        // $lockKey = 'TaskNotificationWhatsAppWorkerController::dailySend';
        // if (!resolve(LockHelper::class)->getLockByName($lockKey, 300)) {
        //     die('Locked');
        // }
        // if (!config('wapi.wapi_notifications_enabled')) {
        //     die('wapi_notifications_is_not_enabled');
        // }

        // $clientService = resolve(ClientService::class);
        // $viewsTaskService = resolve(ViewsTaskService::class);
        // $notifsService = resolve(TaskNotificationWhatsAppMessageService::class);
        
        // $clients = $clientService->findWithEnabledDailyTaskWhatsAppMessageAlert()->filter(function ($client) {
        //     return $client->clientSettings->enable_wapi;
        // });
        // foreach ($clients as $client) {
        //     SystemHelper::doFlush();
        //     resolve(LockHelper::class)->getLockByName($lockKey, 300);
            
        //     $this->printSeparator($client);
        //     $this->printClientInfo($client);

        //     $clientCurrentHour = $this->getClientCurrentHour($client);
        //     if ($clientCurrentHour < 7 || $clientCurrentHour > 12) {
        //         echo '<div> - Client hour is not between 7 and 12 </div>';
        //         continue;
        //     }

        //     try {
        //         $notifWasAlreadySent = $notifsService->dailyWhatsAppMessageWasSentTodayToClient($client);
        //         if ($notifWasAlreadySent) {
        //             echo '<div> - Client daily notification was already sent </div>';
        //             continue;
        //         }

        //         $tasksExpiringToday = $viewsTaskService->findTasksExpiringTodayByClient($client);
        //         if ($tasksExpiringToday->isEmpty()) {
        //             echo '<div> - Client has not expiring tasks today </div>';
        //             continue;
        //         }

        //         $dispatchedNotifs = $notifsService->sendTasksExpiringTodayNotificationWhatsAppMessageToUsers(
        //             $tasksExpiringToday
        //         );
        //         $this->printDispatchedNotificationsInfo($dispatchedNotifs);
        //     } catch (Exception $e) {
        //         dump($e);
        //         report($e);
        //         continue;
        //     }
        // }

        // resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    private function getClientCurrentHour($client): int
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


    private function printDispatchedNotificationsInfo(Collection $dispatchedNotifs): void
    {
        var_dump(
            'Dispatched TaskNotificationsWhatsApp',
            $dispatchedNotifs->map->only(['id', 'task_id'])->toArray()
        );
    }


    private function printSeparator(): void
    {
        echo "<br/><hr/><br/>";
    }

}
