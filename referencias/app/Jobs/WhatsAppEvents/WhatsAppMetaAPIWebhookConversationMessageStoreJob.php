<?php

namespace App\Jobs\WhatsAppEvents;

use Exception;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Services\API\LeadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\API\WapBot\WapBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\MongoDB\WapBot\WapBotConversation;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\WhatsAppSendingMessageService;
use App\DTO\WapBot\WhatsAppMetaAPIWebhookPayloadDTO;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Services\API\WapBot\WapBotConversationService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
use App\Models\MongoDB\WhatsAppMetaAPI\WhatsAppConversationMessage;
use App\Services\API\WhatsAppMetaAPI\WhatsAppConversationMessageService;
use App\Helpers\WhatsAppMetaAPI\WhatsAppConversationRealTimeHelper;


/**
 * queue: ENV_whatsapp_meta_api_webhook_queue
 */
class WhatsAppMetaAPIWebhookConversationMessageStoreJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;

    protected $logUuid = null;


    public function __construct(
        public readonly array $metaWebhookPayload,
    ) {
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        $this->logInfo('Starting WhatsAppMetaAPIWebhookConversationMessageStoreJob');
        $this->logInfo(json_encode($this->metaWebhookPayload));

        $payloadDTO = new WhatsAppMetaAPIWebhookPayloadDTO($this->metaWebhookPayload);
        if ($payloadDTO->isStatusChangeMessage()) {
            $this->logInfo('Payload is a status change message. RETURNING.');
            return true;
        }
        if (!$payloadDTO->isParsableMessage()) {
            $this->logInfo('Payload is not a parsable message. RETURNING.');
            return true;
        }

        $wapConversationMsgService = resolve(WhatsAppConversationMessageService::class);
        $existentWapConversationMsg = $wapConversationMsgService->findOneByPayloadDTO($payloadDTO);
        if ($existentWapConversationMsg) {
            $this->logInfo('Wap Conversation Message already exists. RETURNING.');
            return true;
        }

        $wapConversationMsg = $wapConversationMsgService->createFromWebhookPayloadDTO($payloadDTO);
        $this->logInfo("New wapConversationMsg created");
        $this->logInfo("wapConversationMsg ID: {$wapConversationMsg->id}");
        $this->logInfo("wapConversationMsg hash: {$wapConversationMsg->hash}");

        if ($wapConversationMsg->hasDownloadableMedia()) {
            $wapDispatcher = resolve(WhatsAppEventsDispatcherService::class);
            $wapDispatcher->dispatchWhatsAppMetaAPIWebhookConversationFileStoreJob($wapConversationMsg->id);
            $this->logInfo('FileStoreJob dispatched for media.');
        }

        $this->broadcastRealTimeMessage($wapConversationMsg);

        $this->logInfo('Finished execution.');
        return true;
    }


    /**
     * El webhook de Meta no trae clientId — lo buscamos por la conexión.
     */
    private function broadcastRealTimeMessage(WhatsAppConversationMessage $wapConversationMsg): void
    {
        try {
            $connection = resolve(WhatsAppMetaAPIService::class)->findActiveByPhoneNumberId(
                $wapConversationMsg->metaConnectedPhoneNumberId
            );
            if ($connection && $connection->client_id) {
                resolve(WhatsAppConversationRealTimeHelper::class)->broadcastNewConversationMessage(
                    $wapConversationMsg, $connection->client_id
                );
            }
        } catch (Throwable $e) {
            $this->logInfo("broadcastRealTimeMessage error: {$e}");
            report($e);
        }
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('WhatsAppMetaAPIWebhookConversationMessageStoreJobErrors')->error((string) $e);
    }

    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('WhatsAppMetaAPIWebhookConversationMessageStoreJobInfo');
    }


}
