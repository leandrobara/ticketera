<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use Exception;
use App\Models\User;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use App\Helpers\QueuedJobsCounter;
use Illuminate\Support\Facades\Log;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\WhatsAppMetaAPIConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\API\WhatsAppSendingService;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
    

class SendWhatsAppMetaAPIMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $tries = 2;
    public $timeout = 30;
    public $backoff = 120;
    
    public $logUuid;
    public $lockKey;
    public $wapSendingMsg;

    public function __construct(
        protected int $userId,
        protected int $clientId,
        protected int $whatsAppSendingMessageId,
        protected int $whatsAppMetaAPIConnectionId,
    ) {
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        $this->lockKey = 'SendWhatsAppMetaAPIMessageJobLock:' . $this->userId;
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($this->lockKey, $this->timeout);
        
        $this->initializeLogData(
            userId: $this->userId,
            lockKey: $this->lockKey,
            clientId: $this->clientId,
            whatsAppSendingMessageId: $this->whatsAppSendingMessageId,
            whatsAppMetaAPIConnectionId: $this->whatsAppMetaAPIConnectionId,
        );
        
        $this->wapSendingMsg = WhatsAppSendingMessage::findOrFail($this->whatsAppSendingMessageId);
        if (!$lockIsGranted) {
            $this->requeueThisJob($this->wapSendingMsg);
            return true;
        }

        $this->whatsAppMetaAPIConnection = WhatsAppMetaAPIConnection::findOrFail($this->whatsAppMetaAPIConnectionId);
        $this->logInfo("- LOCK GRANTED [{$this->whatsAppMetaAPIConnectionId}]");

        $invalidToSendErrorMsg = $this->getInvalidToSendErrorMessage($this->wapSendingMsg);
        if ($invalidToSendErrorMsg) {
            resolve(LockHelper::class)->releaseLockByName($this->lockKey);
            $this->logInfo($invalidToSendErrorMsg);
            return true;
        }

        try {
            $wapSending = $this->wapSendingMsg->whatsAppSending;
            if ($wapSending->whatsapp_template_id) {
                $metaResponse = resolve(WhatsAppMetaAPIService::class)->sendTemplateMessage($this->wapSendingMsg);
            } else {
                $metaResponse = resolve(WhatsAppMetaAPIService::class)->sendOpenTextMessage($this->wapSendingMsg);
            }
            $this->dispatchCreateProposalIfApplicable($this->wapSendingMsg);
            $this->dispatchFinishIfEnded($this->wapSendingMsg);
            $this->dispatchSaveSentConversationMessage($this->wapSendingMsg);
            $this->dispatchEnsureWapBotSeedConversation($this->wapSendingMsg, $this->whatsAppMetaAPIConnection);

            resolve(LockHelper::class)->releaseLockByName($this->lockKey);
            $this->logInfo('FINISHED SUCCESFULLY');
        } catch (Throwable $e) {
            resolve(LockHelper::class)->releaseLockByName($this->lockKey);
            
            $this->logInfo('NOT FINISHED. SENDING ERROR: ');
            $this->logException($e, 'info');

            if ($this->attempts() == 1) {
                $this->logException($e, 'error');
            }
            throw $e;
        }
    }


    public function dispatchSaveSentConversationMessage(WhatsAppSendingMessage $wapSendingMsg): void
    {
        resolve(WhatsAppEventsDispatcherService::class)->dispatchWhatsAppMetaAPISentConversationMessageStoreJob(
            $wapSendingMsg->id
        );
    }


    public function dispatchEnsureWapBotSeedConversation(
        WhatsAppSendingMessage $wapSendingMsg,
        WhatsAppMetaAPIConnection $connection,
    ): void {
        resolve(WhatsAppEventsDispatcherService::class)->dispatchWapBotCreateSeedConversationFromMetaAPISendJob(
            botPhoneNumber: $connection->phone_number,
            customerPhoneNumber: $wapSendingMsg->phone_number,
            botMetaPhoneNumberId: $connection->phone_number_id,
        );
    }


    public function dispatchFinishIfEnded(WhatsAppSendingMessage $wapSendingMsg): void
    {
        resolve(WhatsAppEventsDispatcherService::class)->dispatchFinishWhatsAppMetaAPISendingIfEndedJob(
            delaySecs: 15,
            clientId: $wapSendingMsg->client_id,
            wapSendingId: $wapSendingMsg->whatsapp_sending_id,
        );
    }


    protected function requeueThisJob(WhatsAppSendingMessage $wapSendingMsg, int $baseDelaySeconds = 35): void
    {
        // Traigo la cantidad de jobs re-encolados de este mismo $lockKey los ultimos 10 segundos.
        $jobsCounter = resolve(QueuedJobsCounter::class, ['ttlSeconds' => 10]);
        $requeuedJobsCount = $jobsCounter->createOrGet($this->lockKey);
        $nextStepDelay = (int) mt_rand(8, 10);
        $delaySecs = $baseDelaySeconds + ($requeuedJobsCount * $nextStepDelay);

        $this->logInfo('- requeuedJobsCount: ' . $requeuedJobsCount);
        $this->logInfo('- delaySecs: ' . $delaySecs);
        $this->logInfo('   - LOCK NOT GRANTED: JOB REQUEUED');
        
        // Dispatch job again and then delete the current job
        resolve(WhatsAppEventsDispatcherService::class)->dispatchSendWhatsAppMetaAPIMessageJob(
            $wapSendingMsg, $delaySecs
        );
        // Incremento el contador de jobs reencolados
        $requeuedJobsCount = $jobsCounter->increment($this->lockKey);

        $this->delete();
    }


    public function initializeLogData(
        int $userId,
        int $clientId,
        string $lockKey,
        int $whatsAppSendingMessageId,
        int $whatsAppMetaAPIConnectionId,
    ): void {
        $this->logInfo('');
        $this->logInfo('----------------------------------------------------------');
        $this->logInfo('STARTING ' . self::class . ' ...');
        $this->logInfo('- clientId: ' . $clientId);
        $this->logInfo('- userId: ' . $userId);
        $this->logInfo('- whatsAppSendingMessageId: ' . $whatsAppSendingMessageId);
        $this->logInfo('- whatsAppMetaAPIConnectionId: ' . $whatsAppMetaAPIConnectionId);
        $this->logInfo('- lockKey: ' . $lockKey);
        $this->logInfo('- attempt: ' . $this->attempts());
    }


    public function getInvalidToSendErrorMessage(WhatsAppSendingMessage $wapSendingMsg): ?string
    {
        if (!$wapSendingMsg) {
            return '   - ERROR, MANUALLY FINISHED - WAP MSG DOES NOT EXIST';
        }
        if ($wapSendingMsg->cancelled_date) {
            return '   - ERROR, MANUALLY FINISHED - WAP MSG WAS CANCELLED';
        }
        if ($wapSendingMsg->send_attempts >= 2) {
            return '   - ERROR, MANUALLY FINISHED - SEND_ATTEMPTS >= 2';
        }
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
        return null;
    }


    public function dispatchCreateProposalIfApplicable(WhatsAppSendingMessage $wapSendingMsg): void
    {
        if ($wapSendingMsg->is_proposal) {
            resolve(WhatsAppEventsDispatcherService::class)->dispatchCreateProposalAfterWAPIMessageSentJob(
                $wapSendingMsg
            );
        }
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
                'userId' => $this->userId,
                'lockKey' => $this->lockKey,
                'clientId' => $this->clientId,
                'whatsAppSendingMessageId' => $this->whatsAppSendingMessageId,
                'whatsAppMetaAPIConnectionId' => $this->whatsAppMetaAPIConnectionId,
            ];
            $logFunction(json_encode($infoArr));
        }
        
        $logFunction($e->getMessage());

        $trace = collect($e->getTrace())->take(10);
        foreach ($trace as $traceEntry) {
            $logFunction($this->formatTraceEntry($traceEntry));
        }
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


    public function getInfoLog()
    {
        return Log::channel('whatsapp_meta_api_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('whatsapp_meta_api_events_errors');
    }

}
