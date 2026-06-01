<?php

namespace App\Http\Controllers\API\Worker;

use Exception;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\LeadNotificationWhatsAppMessageService;


class LeadNotificationWhatsAppMessageWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(2000);
    }


    public function sendScheduledGrouped(Request $req)
    {
        $lockKey = 'LeadNotificationWhatsAppMessageWorkerController::sendScheduledGrouped';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 30)) {
            die('Locked');
        }
        if (!config('wapi.wapi_notifications_enabled')) {
            die('wapi_notifications_is_not_enabled');
        }
        $service = resolve(LeadNotificationWhatsAppMessageService::class);
        $clients = resolve(ClientService::class)->findWithEnabledNewLeadWhatsAppMessageAlert();
        foreach ($clients as $client) {
            SystemHelper::doFlush();
            $this->printClientInfo($client);

            try {
                $groupedNotifs = $service->findGroupedToSend($client);
                $dispatchedNotifs = $service->sendGroupedNewLeadNotificationWhatsAppMessageToLeadUsers($groupedNotifs);

                resolve(LockHelper::class)->getLockByName($lockKey, 90);
            } catch (Exception $e) {
                dump($e);
                report($e);
                continue;
            }

            $this->printDispatchedNotificationsInfo($dispatchedNotifs);
            $this->printSeparator();
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    private function printClientInfo(Client $client): void
    {
        echo "<h3>- Client ID {$client->id}: {$client->name} </h3> <br/>";
    }


    private function printDispatchedNotificationsInfo(Collection $dispatchedNotifs): void
    {
        var_dump('Dispatched LeadNotificationsWhatsApp', $dispatchedNotifs->map->only(['id', 'success'])->toArray());
    }


    private function printSeparator(): void
    {
        echo "<br/><hr/><br/>";
    }

}
