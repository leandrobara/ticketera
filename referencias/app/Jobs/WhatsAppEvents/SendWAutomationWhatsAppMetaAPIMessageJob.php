<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use Exception;
use App\Models\Tag;
use App\Models\User;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Models\WAutomationLog;
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
use App\Services\API\WAutomations\WAutomationLogService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\Actions\LeadService as ActionsLeadService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
    

class SendWAutomationWhatsAppMetaAPIMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $tries = 1;
    public $timeout = 30;
    public $backoff = 120;
    
    public $logUuid;
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
        
        $this->initializeLogData(
            userId: $this->userId,
            clientId: $this->clientId,
            whatsAppSendingMessageId: $this->whatsAppSendingMessageId,
            whatsAppMetaAPIConnectionId: $this->whatsAppMetaAPIConnectionId,
        );
        
        $this->wapSendingMsg = WhatsAppSendingMessage::findOrFail($this->whatsAppSendingMessageId);
        $this->whatsAppMetaAPIConnection = WhatsAppMetaAPIConnection::findOrFail($this->whatsAppMetaAPIConnectionId);

        $invalidToSendErrorMsg = $this->getInvalidToSendErrorMessage($this->wapSendingMsg);
        if ($invalidToSendErrorMsg) {
            $this->logInfo($invalidToSendErrorMsg);
            return true;
        }

        try {
            $wAutomationLog = $this->wapSendingMsg->wAutomationLog;
            $metaResponse = resolve(WhatsAppMetaAPIService::class)->sendTemplateMessage($this->wapSendingMsg);
            
            $this->dispatchFinishIfEnded($this->wapSendingMsg);
            $this->dispatchSaveSentConversationMessage($this->wapSendingMsg);
            $this->dispatchEnsureWapBotSeedConversation($this->wapSendingMsg, $this->whatsAppMetaAPIConnection);
            $this->executePostWAutomationAction($this->wapSendingMsg, $wAutomationLog);

            resolve(WAutomationLogService::class)->markAsApplied($wAutomationLog);

            $this->logInfo('FINISHED SUCCESFULLY');
        } catch (Throwable $e) {
            $this->logInfo('NOT FINISHED. SENDING ERROR.');
            $this->logInfo((string) $e);

            resolve(WAutomationLogService::class)->markAsNotApplied($wAutomationLog, $e->getMessage());
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


    public function initializeLogData(
        int $userId,
        int $clientId,
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
        $this->logInfo('- attempt: ' . $this->attempts());
    }


    public function getInvalidToSendErrorMessage(WhatsAppSendingMessage $wapSendingMsg): ?string
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


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('SendWAutomationWhatsAppMetaAPIMessageJobErrors')->error((string) $e);
    }

    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('SendWAutomationWhatsAppMetaAPIMessageJobInfo');
    }

}
