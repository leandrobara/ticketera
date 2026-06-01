<?php

namespace App\Http\Controllers\API\Worker;

use Throwable;
use Exception;
use Pusher\Pusher;
use App\Models\User;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Helpers\CronTabHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\ClientService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\API\WAPSenderService;
use App\Models\WhatsAppSendingMessage;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WhatsAppSendingMessageService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;


class WAPSenderWorkerController extends BaseAPIController
{

    const ERRORS_ENABLED_TO_RETRY = [
        'missing_clienty_tab',
        'missing_whatsapp_tab',
        'wap_sender_unreachable',
        'extension_uuid_not_found',
        'getMaybeMeUser is not a function',
        'clienty_url_is_not_unique_in_tabs',
        'UNREACHABLE_CONTENT_EXCEPTION_CODE',
        'user_is_not_synced_with_wap_sender',
        'missing_pusher_channel_subscription',
        'whatsapp_web_user_number_does_not_match',
    ];


    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(500);
    }


    //
    // Intenta nuevamente mensajes que fueron programados pero fallaron al enviarse.
    //
    public function retryFailedWAPSenderScheduledMessages(Request $req)
    {
        $lockKey = 'retryFailedWAPSenderScheduledMessages';
        if (CronTabHelper::workerCronIsRunning($lockKey)) {
            die('Locked: cron is already runnning');
        }
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            die('Locked');
        }

        $this->logSeparator();
        $clientId = (int) $req->input('client_id');
        $clients = resolve(ClientService::class)->findWithEnabledWAPSenderJob(['with' => ['users']]);
        if ($clientId) {
            $clients = $clients->where('id', $clientId);
        }

        $wapSenderService = resolve(WAPSenderService::class);
        $wapSendingMsgService = resolve(WhatsAppSendingMessageService::class);
        $whatsAppEventsDispatcherService = resolve(WhatsAppEventsDispatcherService::class);

        foreach ($clients as $clientIndex => $client) {
            SystemHelper::doFlush();

            $users = $client->users
                ->where('enabled', true)
                ->where('wap_sender_retry_delay_days', '>', 0)
                ->whereNotNull('wap_sender_session_phone_number')
            ;
            if ($users->isNotEmpty()) {
                $this->printAndLogClientInfo($client);
            }

            foreach ($users as $user) {
                try {
                    $this->printAndLogUserInfo($user);

                    $wapSenderMessagesToRetry = $wapSendingMsgService->findFailedWAPSenderScheduledMessagesToRetry(
                        $user, self::ERRORS_ENABLED_TO_RETRY
                    );
                    if ($wapSenderMessagesToRetry->isEmpty()) {
                        $this->printAndLogInfo("[NO MESSAGES TO RETRY]", ['isError' => true]);
                        continue;
                    }

                    $pusherChannelName = $wapSenderService->buildPusherChannelNameByUser($user);
                    $wapSenderService->triggerWAPSyncStatusPusherEvent($pusherChannelName);
                    $this->printAndLogInfo("Pusher SYNC event SENT to '{$pusherChannelName}'. Awaiting response...");

                    $syncResponsesData = $wapSenderService->getWAPSyncStatusResponsesData(
                        user: $user, maxWaitTimeoutSeconds: 5
                    );
                    $this->printAndLogInfo('WAP Sync Status Responses: ' . json_encode($syncResponsesData));
                    
                    $allResponses = $syncResponsesData['allResponses'] ?? [];
                    $successResponse = $syncResponsesData['successResponse'] ?? null;
                    if (!$allResponses) {
                        $this->printAndLogInfo("{$pusherChannelName} SYNC response NOT received", ['isError' => true]);
                        continue;
                    }
                    if (!$successResponse) {
                        $this->printAndLogInfo("{$pusherChannelName} SYNC: NO enabled extension", ['isError' => true]);
                        continue;
                    }

                    foreach ($wapSenderMessagesToRetry as $wapSenderMsgToRetry) {
                        $errorMsg = $this->getInvalidErrorMessage($wapSenderMsgToRetry, $user);
                        if ($errorMsg) {
                            $this->printAndLogNotSuccessDispatch($wapSenderMsgToRetry, $errorMsg);
                            continue;
                        }

                        $delaySecs = ($delaySecs ?? 0) + 8;
                        $wapSendingMsgService->markAsDispatchedToRetry($wapSenderMsgToRetry);
                        $whatsAppEventsDispatcherService->dispatchSendWAPSenderMessageJob(
                            $wapSenderMsgToRetry, $delaySecs
                        );

                        $this->printAndLogSuccessDispatch($wapSenderMsgToRetry, $delaySecs);
                        $this->printSeparator();
                    }
                } catch (Exception $exception) {
                    $this->logException($exception);
                    dump($exception);
                    continue;
                }
            }

            if ($users->isNotEmpty()) {
                $this->printSeparator(2);
            }
            resolve(LockHelper::class)->getLockByName($lockKey, 90);
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function getInvalidErrorMessage(WhatsAppSendingMessage $wapSendingMsgToRetry, User $user): ?string
    {
        if (!$wapSendingMsgToRetry->isWapSenderJobType()) {
            return 'wap_sending_msg_to_retry_is_not_wap_sender_job_type';
        }
        if ($wapSendingMsgToRetry->success) {
            return 'wap_sending_msg_to_retry_was_successfully_sent';
        }
        if (!$wapSendingMsgToRetry->sent_date) {
            return 'wap_sending_msg_to_retry_has_never_being_sent';
        }
        if ($wapSendingMsgToRetry->cancelled_date) {
            return 'wap_sending_msg_to_retry_was_cancelled';
        }
        if (!$wapSendingMsgToRetry->dispatched_date) {
            return 'wap_sending_msg_to_retry_was_never_dispatched';
        }
        if ($wapSendingMsgToRetry->paused_date) {
            return 'wap_sending_msg_to_retry_was_paused';
        }
        if (!$wapSendingMsgToRetry->error_message) {
            return 'wap_sending_msg_to_retry_has_not_any_error_message';
        }
        if ($wapSendingMsgToRetry->user_id != $user->id) {
            return 'wap_sending_msg_to_retry_user_does_not_match';
        }
        if (!$wapSendingMsgToRetry->lead) {
            return 'wap_sending_msg_to_retry_has_no_lead';
        }
        if (!$wapSendingMsgToRetry->leadContactPhone) {
            return 'wap_sending_msg_to_retry_has_no_lead_contact_phone';
        }
        return null;
    }


    private function logException(Throwable $exception): void
    {
        $this->getInfoLog()->info($exception->getMessage());
        $trace = collect($exception->getTrace())->take(10);
        foreach ($trace as $traceEntry) {
            $formattedEntry = sprintf(
                '%s: %s(%s)',
                $traceEntry['file'] ?? 'N/A', $traceEntry['function'] ?? 'N/A', $traceEntry['line'] ?? 'N/A'
            );
            $this->getInfoLog()->info($formattedEntry);
        }
    }


    private function logSeparator(): void
    {
        $this->getInfoLog()->info("");
        $this->getInfoLog()->info("================================================================");
    }


    private function printAndLogClientInfo(Client $client): void
    {
        echo "<h3>- Client ID {$client->id}: {$client->name} </h3> <br/>";
        $this->getInfoLog()->info("----------------------------------------------------------------");
        $this->getInfoLog()->info("client_id: {$client->id}");
        $this->getInfoLog()->info("client_name: {$client->name}");
    }


    private function printAndLogUserInfo(User $user): void
    {
        echo "<h4 style='margin-left: 30px;'>- User ID {$user->id}: {$user->username} </h4>";
        $this->getInfoLog()->info("user_id: {$user->id}");
        $this->getInfoLog()->info("user_username: {$user->username}");
    }


    private function printAndLogNotSuccessDispatch(WhatsAppSendingMessage $wapSendingMsgToRetry, string $errorMsg)
    {
        $msg = "wapSendingMsgToRetry ID: {$wapSendingMsgToRetry->id} [NOT ENABLED TO DISPATCH]";
        $this->printAndLogInfo($msg, ['isError' => true]);
        $this->printAndLogInfo('Error message: ' . $errorMsg, ['isError' => true]);
    }


    private function printAndLogSuccessDispatch(WhatsAppSendingMessage $wapSendingMsgToRetry, int $delaySecs): void
    {
        $msg = "wapSendingMsgToRetry ID: {$wapSendingMsgToRetry->id}";
        $this->printAndLogInfo($msg, ['isSuccess' => true]);
        $this->printAndLogInfo("[RE-DISPATCHED SUCCESSFULLY] - delaySecs: {$delaySecs}", ['isSuccess' => true]);
    }


    private function printAndLogInfo(string $msg, array $opts = []): void
    {
        $errorStyle = ($opts['isError'] ?? false) ? 'color:red;' : '';
        $successStyle = ($opts['isSuccess'] ?? false) ? 'color:green;' : '';
        echo "<p style='margin-left: 60px;{$errorStyle}{$successStyle}'>- {$msg} <p>";
        $this->getInfoLog()->info($msg);
    }


    private function printSeparator(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            echo "<br/><hr/><br/>";
        }
    }


    private function getInfoLog()
    {
        return Log::channel('WAPSenderWorkerController');
    }

}
