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
use App\Services\API\Dispatchers\BrowserEventsDispatcher;
use App\Services\API\Views\TaskService as ViewsTaskService;


class BrowserNotificationsController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(500);
    }


    public function sendBeforeTaskExpiration(Request $req)
    {
        $lockKey = 'BrowserNotificationsController::sendBeforeTaskExpiration';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 900)) {
            die('Locked');
        }
        resolve(LockHelper::class)->getLockByName($lockKey, 900);

        $minutesToExpire = 16;
        $clientService = resolve(ClientService::class);
        $viewsTaskService = resolve(ViewsTaskService::class);
        $browserEventsDispatcher = resolve(BrowserEventsDispatcher::class);

        $clients = $clientService->findAllEnabled()->filter(function ($client) {
            return $client->clientSettings->enable_task_hour_reminder_browser_alert;
        });
        foreach ($clients as $client) {
            SystemHelper::doFlush();
            resolve(LockHelper::class)->getLockByName($lockKey, 900);
            
            $this->printClientInfo($client);

            try {
                $tasksExpiringNow = $viewsTaskService->findTasksExpiringNowByClientAndMinutesToExpire(
                    $client, $minutesToExpire
                );
                $tasksExpiringNow = $tasksExpiringNow->filter(fn ($task) => !$task->expiring_browser_notification_sent);

                foreach ($tasksExpiringNow as $task) {
                    resolve(LockHelper::class)->getLockByName($lockKey, 900);
                    
                    $browserEventsDispatcher->notifyExpiringTask($task);

                    $task->expiring_browser_notification_sent = true;
                    $task->save();
                    var_dump('$task->id', $task->id);
                }
            } catch (Exception $e) {
                dump($e);
                report($e);
            }
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
        // $response = $taskNotifService->sendTasksExpiringNowNotificationEmailsToClients($minutesToExpire);
        // return $this->getSuccessResponse($sentTaskNotifEmailIds);
    }


    private function printClientInfo(Client $client): void
    {
        echo "<h3>- Client ID {$client->id}: {$client->name} </h3>";
    }

}
