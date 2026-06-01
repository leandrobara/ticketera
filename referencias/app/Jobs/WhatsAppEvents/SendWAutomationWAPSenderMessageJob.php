<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use Exception;
use Pusher\Pusher;
use App\Models\Tag;
use App\Models\User;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Models\WAutomationLog;
use App\Services\API\UserService;
use App\Helpers\QueuedJobsCounter;
use Illuminate\Support\Facades\Cache;
use App\Models\WhatsAppSendingMessage;
use App\Services\API\WAPSenderService;
use App\Helpers\WhatsAppVariablesHelper;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\API\WhatsAppSendingService;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\WAutomations\WAutomationLogService;
use App\Services\API\Actions\LeadService as ActionsLeadService;
use App\Jobs\WhatsAppEvents\Traits\InjectWAutomationWapSenderLog;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;


class SendWAutomationWAPSenderMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, InjectWAutomationWapSenderLog;

    public $tries = 1;
    public $timeout = 50;
    public $backoff = 120;
    
    public $logUuid;
    public $lockKey;
    public $wapSendingMsg;
    public $pusherChannelName;
    public $browserTrackingKey;
    public $pusherSyncStatusKey;

    public int $userId;
    public int $clientId;
    public string $sessionPhoneNumber;
    public int $whatsAppSendingMessageId;


    public function __construct(
        int $whatsAppSendingMessageId,
        int $clientId,
        int $userId,
        string $sessionPhoneNumber,
    ) {
        $this->userId = $userId;
        $this->clientId = $clientId;
        $this->sessionPhoneNumber = $sessionPhoneNumber;
        $this->whatsAppSendingMessageId = $whatsAppSendingMessageId;
    }


    public function handle()
    {
        $wapSenderService = resolve(WAPSenderService::class);
        $wapSendingService = resolve(WhatsAppSendingService::class);

        $this->logUuid = $this->getJobUniqId();
        $this->browserTrackingKey = 'wap-sender-result-' . $this->logUuid;
        $this->pusherChannelName = $wapSenderService->buildPusherChannelName(
            $this->clientId, $this->sessionPhoneNumber
        );
        $this->pusherSyncStatusKey = $wapSenderService->buildPusherSyncStatusKey(
            $this->clientId, $this->sessionPhoneNumber
        );

        $this->lockKey = 'UserSendWAPSenderJobLock:' . $this->sessionPhoneNumber;
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($this->lockKey, $this->timeout);
        
        $this->initializeLogData(
            userId: $this->userId,
            lockKey: $this->lockKey,
            clientId: $this->clientId,
            sessionPhoneNumber: $this->sessionPhoneNumber,
            browserTrackingKey: $this->browserTrackingKey,
            pusherSyncStatusKey: $this->pusherSyncStatusKey,
            whatsAppSendingMessageId: $this->whatsAppSendingMessageId,
        );

        $this->wapSendingMsg = WhatsAppSendingMessage::findOrFail($this->whatsAppSendingMessageId);
        if (!$lockIsGranted) {
            $this->requeueThisJob($this->wapSendingMsg);
            return true;
        }
        $this->logInfo("- LOCK GRANTED [{$this->sessionPhoneNumber}]");

        $invalidSendingErrorMsg = $this->getInvalidSendingErrorMessage($this->wapSendingMsg);
        if ($invalidSendingErrorMsg) {
            resolve(LockHelper::class)->releaseLockByName($this->lockKey);
            $this->logInfo($invalidSendingErrorMsg);
            return true;
        }

        try {
            $wAutomationLog = $this->wapSendingMsg->wAutomationLog;
            
            $wapSenderService->triggerWAPSyncStatusPusherEvent($this->pusherChannelName);
            $this->logInfo("Pusher SYNC event SENT to '{$this->pusherChannelName}'. Awaiting response...");
            
            $syncResponsesData = $wapSenderService->getWAPSyncStatusResponsesData(
                user: $this->wapSendingMsg->user, maxWaitTimeoutSeconds: 5
            );
            $this->logInfo('WAP Sync Status Responses: ' . json_encode($syncResponsesData));
            $allResponses = $syncResponsesData['allResponses'] ?? [];
            $successResponse = $syncResponsesData['successResponse'] ?? null;
            if (!$allResponses) {
                $this->logInfo('Pusher SYNC event: response TIMEOUT');
                $wapSendingService->markMessageAsSent($this->wapSendingMsg, false, 'wap_sender_unreachable');
                resolve(LockHelper::class)->releaseLockByName($this->lockKey);
                throw new Exception('wap_sender_unreachable');
            }
            if (!$successResponse) {
                $this->logInfo('Pusher SYNC event: no enabled extension found');
                $errorStr = array_values($allResponses)[0]['error'] ?? 'wap_sender_unreachable';
                $wapSendingService->markMessageAsSent($this->wapSendingMsg, false, $errorStr);
                resolve(LockHelper::class)->releaseLockByName($this->lockKey);
                throw new Exception($errorStr);
            }
            
            $syncedExtensionUUID = $successResponse['extensionUUID'] ?? null;
            if (!$syncedExtensionUUID) {
                $this->logInfo('Pusher SYNC event: extensionUUID not found.');
                $this->logInfo('successResponse: ' . json_encode($successResponse));
                $wapSendingService->markMessageAsSent($this->wapSendingMsg, false, 'extension_uuid_not_found');
                resolve(LockHelper::class)->releaseLockByName($this->lockKey);
                throw new Exception('extension_uuid_not_found');
            }

            $chatMessage = $this->wapSendingMsg->whatsAppSending->WhatsAppSendingMessageText->message;
            $chatMessage = WhatsAppVariablesHelper::replaceVariables(
                $chatMessage, $this->wapSendingMsg->leadContactPhone, $this->wapSendingMsg->user
            );
            $phoneNumberTo = $this->wapSendingMsg->phone_number;
            $redirectWapiToPhone = config('wapi.redirect_wapi_to_phone', null);
            if ($redirectWapiToPhone) {
                $phoneNumberTo = $redirectWapiToPhone;
                $this->logInfo("Destination phone number changed by .ENV to: {$redirectWapiToPhone}");
            }

            $attachmentData = null;
            $wapAttachment = $this->wapSendingMsg->whatsAppSending->whatsAppAttachment;
            if ($wapAttachment) {
                $downloadUrl = route('whatsapp-sender-extension.download-attachment', [
                    'wapSendingMsg' => $this->wapSendingMsg->id, 'attachmentHash' => $wapAttachment->hash
                ]);
                $subdomain = $this->wapSendingMsg->client->subdomain;
                $downloadUrl = Str::replaceFirst('clienty.', "{$subdomain}.clienty.", $downloadUrl);
                $attachmentData = [
                    'url' => $downloadUrl,
                    'hash' => $wapAttachment->hash,
                    'mimeType' => $wapAttachment->mime_type,
                    'extension' => $wapAttachment->extension,
                    'filename' => $wapAttachment->original_filename,
                ];
            }

            $replaceWAPIFromPhone = config('wapi.replace_wapi_from_phone', null);
            if ($replaceWAPIFromPhone) {
                $this->pusherChannelName = $wapSenderService->buildPusherChannelName(
                    $this->clientId, $replaceWAPIFromPhone
                );
                $this->logInfo("Sender phone number changed by .ENV to: {$replaceWAPIFromPhone}");
            }

            $wapSenderService->sendNewWAPMessage(
                userId: $this->userId,
                wAutomationLogId: $wAutomationLog->id,
                clientId: $this->clientId,
                chatMessage: $chatMessage,
                attachment: $attachmentData,
                phoneNumber: $phoneNumberTo,
                targetExtensionUUID: $syncedExtensionUUID,
                pusherChannelName: $this->pusherChannelName,
                browserTrackingKey: $this->browserTrackingKey,
                whatsAppSendingMessageId: $this->wapSendingMsg->id,
                fromPhoneNumber: $replaceWAPIFromPhone ?: $this->sessionPhoneNumber,
            );
            $logMsg = "Pusher message event SENT to channel: '{$this->pusherChannelName}' - ";
            $logMsg .= "targetExtensionUUID: '{$syncedExtensionUUID}'. Awaiting response...";
            $this->logInfo($logMsg);

            $sendResponse = $wapSenderService->getSentWAPMessageResponse(
                browserTrackingKey: $this->browserTrackingKey, maxWaitTimeoutSeconds: 25
            );
            if (!$sendResponse) {
                $this->logInfo('Pusher message event response TIMEOUT');
                $wapSendingService->markMessageAsSent($this->wapSendingMsg, false, 'wap_sender_unreachable');
                resolve(LockHelper::class)->releaseLockByName($this->lockKey);
                throw new Exception('wap_sender_unreachable');
            }
            $this->logInfo('Pusher event response RECEIVED: ' . json_encode($sendResponse));
            if (!($sendResponse['success']) ?? false) {
                $errorStr = $sendResponse['error'] ?? 'unknown_error';
                $wapSendingService->markMessageAsSent($this->wapSendingMsg, false, $errorStr);
                resolve(LockHelper::class)->releaseLockByName($this->lockKey);
                throw new Exception($errorStr);
            }

            $wapSendingService->markMessageAsSent($this->wapSendingMsg, true);
            
            $this->executePostWAutomationAction($this->wapSendingMsg, $wAutomationLog);

            resolve(WAutomationLogService::class)->markAsApplied($wAutomationLog);
            resolve(LockHelper::class)->delayReleaseLockByName($this->lockKey, 7);

            $this->dispatchFinishIfEnded($this->wapSendingMsg);

            $this->logInfo('FINISHED SUCCESFULLY');
        } catch (Throwable $e) {
            resolve(LockHelper::class)->releaseLockByName($this->lockKey);

            $this->logInfo('NOT FINISHED. SENDING ERROR: ');
            $this->logException($e, 'info');

            if ($this->attempts() == 1) {
                $this->logException($e, 'error');
            }
            resolve(WAutomationLogService::class)->markAsNotApplied($wAutomationLog, $e->getMessage());
            throw $e;
        }
    }


    protected function requeueThisJob(
        WhatsAppSendingMessage $wapSendingMsg,
        int $baseDelaySeconds = 20,
    ): void {
        // Traigo la cantidad de jobs re-encolados de este mismo $lockKey los ultimos 10 segundos.
        $jobsCounter = resolve(QueuedJobsCounter::class, ['ttlSeconds' => 10]);
        $requeuedJobsCount = $jobsCounter->createOrGet($this->lockKey);
        $nextStepDelay = (int) mt_rand(8, 10);
        $delaySecs = $baseDelaySeconds + ($requeuedJobsCount * $nextStepDelay);

        $this->logInfo('- requeuedJobsCount: ' . $requeuedJobsCount);
        $this->logInfo('- delaySecs: ' . $delaySecs);
        $this->logInfo('   - LOCK NOT GRANTED: JOB REQUEUED');
        
        // Dispatch job again and then delete the current job
        resolve(WhatsAppEventsDispatcherService::class)->dispatchSendWAutomationWAPSenderMessageJob(
            $wapSendingMsg, $delaySecs
        );
        // Incremento el contador de jobs reencolados
        $requeuedJobsCount = $jobsCounter->increment($this->lockKey);
        
        $this->delete();
    }


    public function executePostWAutomationAction(
        WhatsAppSendingMessage $wapSendingMsg,
        WAutomationLog $wAutomationLog
    ): bool {
        if (!$wAutomationLog->wautomation_sequence_step_id) {
            return false;
        }
        
        $tagsToAdd = $wAutomationLog?->wAutomationSequenceStep?->tagsToAdd;
        if ($tagsToAdd && $tagsToAdd->isNotEmpty()) {
            $leadTags = $wapSendingMsg->lead->tags;
            $tagsToAdd = $tagsToAdd->filter(function (Tag $tagToAdd) use ($leadTags) {
                $alreadyAssignedTag = $leadTags->where('id', $tagToAdd->id)->first();
                return $alreadyAssignedTag ? false : true;
            });
            if ($tagsToAdd->isNotEmpty()) {
                $allTags = $leadTags->merge($tagsToAdd);
                if ($allTags) {
                    resolve(ActionsLeadService::class)->setLeadTags($wapSendingMsg->lead, $allTags);
                }
            }
        }
        
        $statusToAdd = $wAutomationLog?->wAutomationSequenceStep?->statusToAdd;
        if ($statusToAdd) {
            if ($wapSendingMsg->lead->status_id != $statusToAdd->id) {
                resolve(ActionsLeadService::class)->changeStatus($wapSendingMsg->lead, $statusToAdd);
            }
        }
        return true;
    }


    public function dispatchFinishIfEnded(WhatsAppSendingMessage $wapSendingMsg): void
    {
        resolve(WhatsAppEventsDispatcherService::class)->dispatchFinishWAPSenderSendingIfEndedJob(
            $wapSendingMsg->whatsapp_sending_id, $wapSendingMsg->client_id
        );
    }


    public function initializeLogData(
        int $userId,
        int $clientId,
        string $lockKey,
        string $sessionPhoneNumber,
        string $browserTrackingKey,
        string $pusherSyncStatusKey,
        int $whatsAppSendingMessageId,
    ): void {
        $this->logInfo('');
        $this->logInfo('----------------------------------------------------------');
        $this->logInfo('STARTING ' . self::class . ' ...');
        $this->logInfo('- clientId: ' . $clientId);
        $this->logInfo('- userId: ' . $userId);
        $this->logInfo('- whatsAppSendingMessageId: ' . $whatsAppSendingMessageId);
        $this->logInfo('- sessionPhoneNumber: ' . $sessionPhoneNumber);
        $this->logInfo('- browserTrackingKey: ' . $browserTrackingKey);
        $this->logInfo('- pusherSyncStatusKey: ' . $pusherSyncStatusKey);
        $this->logInfo('- lockKey: ' . $lockKey);
        $this->logInfo('- attempt: ' . $this->attempts());
    }


    public function getInvalidSendingErrorMessage(WhatsAppSendingMessage $wapSendingMsg): ?string
    {
        if (!$wapSendingMsg) {
            return '   - ERROR, MANUALLY FINISHED - WAP MSG DOES NOT EXIST';
        }
        if (!$wapSendingMsg->wAutomationLog) {
            return '   - ERROR, MANUALLY FINISHED - WAP MSG DOES NOT HAVE WAUTOMATIONLOG';
        }
        if ($wapSendingMsg->cancelled_date) {
            return '   - ERROR, MANUALLY FINISHED - WAP MSG WAS CANCELLED';
        }
        // if ($wapSendingMsg->send_attempts >= 2) {
        //     return '   - ERROR, MANUALLY FINISHED - SEND_ATTEMPTS >= 2';
        // }
        // La gestión de reenvío acá marca sent_date y success=false si falla el primer intento
        // Esto es para poder crear el WAutomationLog, y para poder intentar más de una vez el envío.
        if ($wapSendingMsg->sent_date && $wapSendingMsg->success) {
            return '   - ERROR, MANUALLY FINISHED - ALREADY SENT';
        }
        if (!$wapSendingMsg->lead) {
            return '   - ERROR, MANUALLY FINISHED - NON EXISTENT LEAD';
        }
        if (!$wapSendingMsg->leadContactPhone) {
            return '   - ERROR, MANUALLY FINISHED - NON EXISTENT LEAD CONTACT PHONE';
        }
        if ($wapSendingMsg->paused_date) {
            return '   - ERROR, MANUALLY FINISHED - WAP MSG IS PAUSED';
        }
        if (!$wapSendingMsg->dispatched_date) {
            return '   - ERROR, MANUALLY FINISHED - WAP MSG WAS NOT DISPATCHED';
        }
        if (!$wapSendingMsg->user->wap_sender_session_phone_number) {
            return '   - ERROR, MANUALLY FINISHED - WAP MSG USER HAS NO WAP_SENDER_SESSION_PHONE_NUMBER';
        }
        if (!$wapSendingMsg->client->clientSettings->enable_whatsapp_sender_job_sending) {
            return '   - ERROR, MANUALLY FINISHED - enable_whatsapp_sender_job_sending IS NOT ENABLED';
        }
        return null;
    }


    protected function logException(Throwable $e, string $logType = 'info')
    {
        $logFunction = $logType === 'error'
            ? fn($msg) => $this->getErrorLog()->error($msg)
            : fn($msg) => $this->logInfo($msg)
        ;
    
        if ($logType === 'error') {
            $dateStr = '[' . now()->format('Y-m-d H:i:s') . '] ';
            $logFunction($dateStr);
        }

        $infoStr = '';
        if ($this->wapSendingMsg) {
            $infoArr = [
                'leadId' => $this->wapSendingMsg->lead_id,
                'userId' => $this->wapSendingMsg->user_id,
                'phoneNumber' => $this->wapSendingMsg->phone_number,
                'whatsAppSendingMessageId' => $this->wapSendingMsg->id,
                'WAPSenderSessionPhoneNumber' => $this->wapSendingMsg->user->wap_sender_session_phone_number,
            ];
            $logFunction(json_encode($infoArr));
        }
        
        $logFunction($e->getMessage());

        $trace = collect($e->getTrace())->take(10);
        foreach ($trace as $traceEntry) {
            $logFunction($this->formatTraceEntry($traceEntry));
        }
    }


    protected function getJobUniqId()
    {
        $uuid = (string) Str::orderedUuid();
        $uuidLastPart = substr(strrchr($uuid, '-'), 1);
        $jobUniqId = $this->clientId . '-'
            . $this->userId . '-'
            . $this->sessionPhoneNumber . '-'
            . $this->whatsAppSendingMessageId . '-'
            . $uuidLastPart
        ;
        return $jobUniqId;
    }


    protected function formatTraceEntry(array $traceEntry): string
    {
        return sprintf(
            '%s: %s(%s)', $traceEntry['file'] ?? 'N/A', $traceEntry['function'] ?? 'N/A', $traceEntry['line'] ?? 'N/A'
        );
    }


    public function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }


    public function failed(Throwable $e)
    {
        $this->logException($e, 'error');
    }

}
