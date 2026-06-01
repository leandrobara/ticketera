<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use Exception;
use App\Models\User;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Services\API\WAPIService;
use App\Services\API\UserService;
use App\Helpers\QueuedJobsCounter;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\API\WhatsAppSendingService;
use App\Jobs\WhatsAppEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
    

class SendWAPIMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;
    
    public $tries = 2;
    public $backoff = 120;
    public $timeout = 180;
    
    public $logUuid;
    public $lockKey;
    public $wapSendingMsg;

    public ?int $userId = null;
    public ?int $clientId = null;
    public int $whatsAppSendingMessageId;
    public ?string $wapiSessionPhoneNumber = null;


    public function __construct(
        int $whatsAppSendingMessageId,
        ?int $clientId = null,
        ?int $userId = null,
        ?string $wapiSessionPhoneNumber = null,
    ) {
        $this->userId = $userId;
        $this->clientId = $clientId;
        $this->whatsAppSendingMessageId = $whatsAppSendingMessageId;
        $this->wapiSessionPhoneNumber = $wapiSessionPhoneNumber ?? null;
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        $sessionPhoneNumber = $this->wapiSessionPhoneNumber;
        $this->lockKey = 'UserSendWAPIJobLock:' . $sessionPhoneNumber;
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($this->lockKey, $this->timeout);
        
        $this->initializeLogData(
            $this->whatsAppSendingMessageId, $this->clientId, $this->userId, $sessionPhoneNumber, $this->lockKey
        );
        
        $this->wapSendingMsg = WhatsAppSendingMessage::findOrFail($this->whatsAppSendingMessageId);



        //
        // FF. WAPI CANCELADO.
        //
        resolve(WhatsAppSendingService::class)->markMessageAsSent(
            $this->wapSendingMsg, false, 'WAPI_STOPPED_BY_FF'
        );
        $this->logInfo('- WAPI_STOPPED_BY_FF');
        return true;
        //



        if (!$lockIsGranted) {
            $this->requeueThisJob($this->wapSendingMsg);
            return true;
        }
        $this->logInfo("- LOCK GRANTED [{$sessionPhoneNumber}]");

        // Este servicio/método usa cache
        $user = resolve(UserService::class)->findOneByUserIdAndClientId($this->userId, $this->clientId);
        if ($user?->wapi_is_paused) {
            $this->requeueThisJob(
                isPausedWapiRequeue: true,
                wapSendingMsg: $this->wapSendingMsg,
                baseDelaySeconds: $user?->wapi_pause_delay_seconds ?? 300,
            );
            resolve(LockHelper::class)->releaseLockByName($this->lockKey);
            return true;
        }

        $invalidToSendErrorMsg = $this->getInvalidToSendErrorMessage($this->wapSendingMsg);
        if ($invalidToSendErrorMsg) {
            resolve(LockHelper::class)->releaseLockByName($this->lockKey);
            $this->logInfo($invalidToSendErrorMsg);
            return true;
        }

        try {
            $WAPIResponse = resolve(WAPIService::class)->sendMessage($this->wapSendingMsg);
            
            $this->dispatchFinishIfEnded($this->wapSendingMsg);
            $this->dispatchCreateProposalIfApplicable($this->wapSendingMsg);
            
            resolve(LockHelper::class)->delayReleaseLockByName($this->lockKey, 7);

            $this->logInfo('FINISHED SUCCESFULLY');
        } catch (Throwable $e) {
            resolve(LockHelper::class)->releaseLockByName($this->lockKey);
            
            $this->logInfo('NOT FINISHED. SENDING ERROR: ');
            $this->logException($e, 'info');

            if ($this->attempts() == 1) {
                $this->logException($e, 'error');
            }

            $this->dispatchFinishIfEnded($this->wapSendingMsg);
            throw $e;
        }
    }


    protected function requeueThisJob(
        WhatsAppSendingMessage $wapSendingMsg,
        int $baseDelaySeconds = 35,
        bool $isPausedWapiRequeue = false,
    ): void {
        // Traigo la cantidad de jobs re-encolados de este mismo $lockKey los ultimos 10 segundos.
        $jobsCounter = resolve(QueuedJobsCounter::class, ['ttlSeconds' => 10]);
        $requeuedJobsCount = $jobsCounter->createOrGet($this->lockKey);
        $nextStepDelay = (int) mt_rand(8, 10);
        $delaySecs = $baseDelaySeconds + ($requeuedJobsCount * $nextStepDelay);

        $this->logInfo('- requeuedJobsCount: ' . $requeuedJobsCount);
        $this->logInfo('- delaySecs: ' . $delaySecs);
        if (!$isPausedWapiRequeue) {
            $this->logInfo('   - LOCK NOT GRANTED: JOB REQUEUED');
        }
        if ($isPausedWapiRequeue) {
            $this->logInfo('   - USER WAPI IS PAUSED: JOB REQUEUED');
        }
        
        // Dispatch job again and then delete the current job
        resolve(WhatsAppEventsDispatcherService::class)->dispatchSendWAPIMessageJob($wapSendingMsg, $delaySecs);
        // Incremento el contador de jobs reencolados
        $requeuedJobsCount = $jobsCounter->increment($this->lockKey);

        $this->delete();
    }


    public function initializeLogData(
        int $wapSendingMsgId,
        int $clientId,
        int $userId,
        string $sessionPhoneNumber,
        string $lockKey,
    ): void {
        $this->logInfo('');
        $this->logInfo('----------------------------------------------------------');
        $this->logInfo('STARTING ' . self::class . ' ...');
        $this->logInfo('- clientId: ' . $clientId);
        $this->logInfo('- userId: ' . $userId);
        $this->logInfo('- whatsAppSendingMessageId: ' . $wapSendingMsgId);
        $this->logInfo('- sessionPhoneNumber: ' . $sessionPhoneNumber);
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


    public function dispatchFinishIfEnded(WhatsAppSendingMessage $wapSendingMsg): void
    {
        resolve(WhatsAppEventsDispatcherService::class)->dispatchFinishWAPISendingIfEndedJob(
            $wapSendingMsg->whatsapp_sending_id, $wapSendingMsg->client_id
        );
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
                'leadId' => $this->wapSendingMsg->lead_id,
                'userId' => $this->wapSendingMsg->user_id,
                'phoneNumber' => $this->wapSendingMsg->phone_number,
                'whatsAppSendingMessageId' => $this->wapSendingMsg->id,
                'wapiSessionPhoneNumber' => $this->wapSendingMsg->user->wapi_session_phone_number,
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

}
