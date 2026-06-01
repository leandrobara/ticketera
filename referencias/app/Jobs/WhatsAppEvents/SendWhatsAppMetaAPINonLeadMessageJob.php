<?php

namespace App\Jobs\WhatsAppEvents;

use Exception;
use Throwable;
use Illuminate\Support\Str;
use App\Helpers\LockHelper;
use Illuminate\Bus\Queueable;
use App\Models\WhatsAppTemplate;
use App\Helpers\QueuedJobsCounter;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use App\Helpers\WhatsAppAttachmentHelper;
use App\Models\WhatsAppMetaAPIConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\API\WhatsAppTemplateService;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
use App\Helpers\WhatsAppMetaAPI\WhatsAppConversationRealTimeHelper;
use App\Services\API\WhatsAppMetaAPI\WhatsAppConversationMessageService;


class SendWhatsAppMetaAPINonLeadMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;

    protected $logUuid;
    protected $lockKey;


    public function __construct(
        public readonly int $userId,
        public readonly int $clientId,
        public readonly string $customerPhoneNumber,
        public readonly int $whatsAppMetaAPIConnectionId,
        public readonly ?string $chatMessage = null,
        public readonly ?int $whatsAppTemplateId = null,
        public readonly array $bodyVariables = [],
        public readonly array $headerVariables = [],
        public readonly ?array $conversationMessageMedia = null,
    ) {
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        $whatsAppTemplate = null;

        $this->lockKey = 'SendWhatsAppMetaAPINonLeadMessageJob:' . $this->userId;
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($this->lockKey, 5);

        $this->logInfo('----------------------------------------------------------');
        $this->logInfo('STARTING ' . self::class);
        $this->logInfo("- clientId: {$this->clientId}");
        $this->logInfo("- userId: {$this->userId}");
        $this->logInfo("- customerPhoneNumber: {$this->customerPhoneNumber}");
        $this->logInfo("- whatsAppMetaAPIConnectionId: {$this->whatsAppMetaAPIConnectionId}");
        $this->logInfo("- lockKey: {$this->lockKey}");
        $this->logInfo("- attempt: {$this->attempts()}");

        // if (!$lockIsGranted) {
        //     $this->requeueThisJob();
        //     return true;
        // }
        $this->logInfo('- LOCK GRANTED');

        try {
            $whatsAppMetaAPIConnection = resolve(WhatsAppMetaAPIService::class)->findWhatsAppMetaAPIConnectionById(
                $this->whatsAppMetaAPIConnectionId
            );
            if (!$whatsAppMetaAPIConnection) {
                throw new Exception("WhatsAppMetaAPIConnection not found: {$this->whatsAppMetaAPIConnectionId}");
            }

            $conversationMessageMedia = $this->conversationMessageMedia ?? [];
            $mediaFilename = $conversationMessageMedia['filename'] ?? null;
            $metaMediaType = $conversationMessageMedia['metaMediaType'] ?? null;
            $isVoiceNote = (bool) ($conversationMessageMedia['isVoiceNote'] ?? false);
            $metaMediaId = $conversationMessageMedia['metaId'] ?? $conversationMessageMedia['id'] ?? null;

            // Envío del mensaje vía Meta API: texto libre, media (audio/imagen/video/documento), o template
            $wapMetaAPIHelper = resolve(WhatsAppMetaAPIHelper::class);
            if ($metaMediaId) {
                $this->logInfo("- Sending media message (type: {$metaMediaType}, metaMediaId: {$metaMediaId})");
                
                $metaResponse = $wapMetaAPIHelper->sendMediaMessage(
                    mediaId: $metaMediaId,
                    filename: $mediaFilename,
                    isVoiceNote: $isVoiceNote,
                    mediaType: $metaMediaType,
                    connection: $whatsAppMetaAPIConnection,
                    toPhoneNumber: $this->customerPhoneNumber,
                );
            } elseif ($this->chatMessage) {
                $this->logInfo('- Sending open text message');

                $metaResponse = $wapMetaAPIHelper->sendTextMessage(
                    messageText: $this->chatMessage,
                    connection: $whatsAppMetaAPIConnection,
                    toPhoneNumber: $this->customerPhoneNumber,
                );
            } else {
                $whatsAppTemplate = resolve(WhatsAppTemplateService::class)->findWhatsAppTemplateById(
                    $this->whatsAppTemplateId
                );
                if (!$whatsAppTemplate) {
                    throw new Exception("WhatsAppTemplate not found: {$this->whatsAppTemplateId}");
                }
                $this->logInfo("- Sending template message: {$whatsAppTemplate->meta_name}");

                // Attachment original del template (en v1 no se permite reemplazo)
                $attachmentData = [];
                $whatsAppAttachment = $whatsAppTemplate->whatsAppAttachment;
                if ($whatsAppAttachment) {
                    $attachmentData = [
                        'caption' => null,
                        'type' => $whatsAppAttachment->getMetaMediaType(),
                        'filename' => $whatsAppAttachment->original_filename,
                        'link' => resolve(WhatsAppAttachmentHelper::class)->getTemporaryUrl($whatsAppAttachment, 30),
                    ];
                }

                $metaResponse = $wapMetaAPIHelper->sendTemplateMessage(
                    languageCode: 'es_ES',
                    attachmentData: $attachmentData,
                    bodyVariables: $this->bodyVariables,
                    headerVariables: $this->headerVariables,
                    toPhoneNumber: $this->customerPhoneNumber,
                    templateName: $whatsAppTemplate->meta_name,
                    accessToken: $whatsAppMetaAPIConnection->access_token,
                    phoneNumberId: $whatsAppMetaAPIConnection->phone_number_id,
                );
            }

            $metaMessageId = $metaResponse['messages'][0]['id'] ?? null;
            $this->logInfo("- metaMessageId: {$metaMessageId}");

            // El guardado y broadcast no deben fallar el job (un retry reenviaría el mensaje)
            $this->saveConversationMessageAndBroadcast($whatsAppMetaAPIConnection, $metaMessageId, $whatsAppTemplate);

            resolve(LockHelper::class)->releaseLockByName($this->lockKey);
            $this->logInfo('FINISHED SUCCESFULLY');
        } catch (Throwable $e) {
            resolve(LockHelper::class)->releaseLockByName($this->lockKey);
            $this->logInfo($e->getMessage());
            throw $e;
        }
    }


    /**
     * Guarda el mensaje de conversación en MongoDB y hace broadcast Pusher.
     * Si falla alguna de las dos operaciones, no se propaga la excepción.
     * Esto evita que un retry del job reenvíe el mensaje a Meta (el mensaje ya se envió).
     */
    private function saveConversationMessageAndBroadcast(
        WhatsAppMetaAPIConnection $whatsAppMetaAPIConnection,
        ?string $metaMessageId,
        ?WhatsAppTemplate $whatsAppTemplate = null,
    ): void {
        try {
            $wapConversationMsg = resolve(WhatsAppConversationMessageService::class)->createFromNonLeadSentMessage(
                userId: $this->userId,
                clientId: $this->clientId,
                metaMessageId: $metaMessageId,
                chatMessage: $this->chatMessage,
                bodyVariables: $this->bodyVariables,
                whatsAppTemplate: $whatsAppTemplate,
                headerVariables: $this->headerVariables,
                customerPhoneNumber: $this->customerPhoneNumber,
                whatsAppMetaAPIConnection: $whatsAppMetaAPIConnection,
                conversationMessageMedia: $this->conversationMessageMedia,
            );
            $this->logInfo("- whatsAppConversationMessage created: {$wapConversationMsg->id}");

            resolve(WhatsAppConversationRealTimeHelper::class)->broadcastNewConversationMessage(
                $wapConversationMsg, $this->clientId
            );
        } catch (Throwable $e) {
            $this->logInfo("- saveConversationMessageAndBroadcast error: {$e->getMessage()}");
            report($e);
        }
    }


    protected function requeueThisJob(int $baseDelaySeconds = 5): void
    {
        $jobsCounter = resolve(QueuedJobsCounter::class, ['ttlSeconds' => 10]);
        $requeuedJobsCount = $jobsCounter->createOrGet($this->lockKey);
        $nextStepDelay = (int) mt_rand(8, 10);
        $delaySecs = $baseDelaySeconds + ($requeuedJobsCount * $nextStepDelay);

        $this->logInfo('- requeuedJobsCount: ' . $requeuedJobsCount);
        $this->logInfo('- delaySecs: ' . $delaySecs);
        $this->logInfo('   - LOCK NOT GRANTED: JOB REQUEUED');

        resolve(WhatsAppEventsDispatcherService::class)->dispatchSendWhatsAppMetaAPINonLeadMessageJob(
            userId: $this->userId,
            delaySecs: $delaySecs,
            clientId: $this->clientId,
            chatMessage: $this->chatMessage,
            bodyVariables: $this->bodyVariables,
            headerVariables: $this->headerVariables,
            whatsAppTemplateId: $this->whatsAppTemplateId,
            customerPhoneNumber: $this->customerPhoneNumber,
            conversationMessageMedia: $this->conversationMessageMedia,
            whatsAppMetaAPIConnectionId: $this->whatsAppMetaAPIConnectionId,
        );

        $jobsCounter->increment($this->lockKey);
        $this->delete();
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error("[{$this->logUuid}] | " . $e->getMessage());
    }


    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('whatsapp_meta_api_non_lead_sender_info');
    }

    protected function getErrorLog()
    {
        return Log::channel('whatsapp_meta_api_non_lead_sender_errors');
    }
}
