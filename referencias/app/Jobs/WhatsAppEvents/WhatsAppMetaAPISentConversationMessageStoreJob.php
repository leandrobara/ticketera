<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\WhatsAppSendingMessageService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppConversationMessageService;
use App\Helpers\WhatsAppMetaAPI\WhatsAppConversationRealTimeHelper;


/**
 * queue: ENV_whatsapp_meta_api_webhook_queue
 */
class WhatsAppMetaAPISentConversationMessageStoreJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;

    protected $logUuid = null;


    public function __construct(
        public readonly int $whatsAppSendingMessageId,
    ) {
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        $this->logInfo('Starting WhatsAppMetaAPISentConversationMessageStoreJob');
        $this->logInfo("whatsAppSendingMessageId: {$this->whatsAppSendingMessageId}");

        $wapSendingMsg = resolve(WhatsAppSendingMessageService::class)->findOneToSaveAsWhatsAppConversationMessage(
            $this->whatsAppSendingMessageId
        );

        if (!$wapSendingMsg) {
            $this->logInfo('WhatsAppSendingMessage not found. RETURNING.');
            return true;
        }
        if (!$wapSendingMsg->success) {
            $this->logInfo('Message was not sent successfully. RETURNING.');
            return true;
        }
        if (!$wapSendingMsg->meta_id) {
            $this->logInfo('Message has no meta_id. RETURNING.');
            return true;
        }

        $wapConversationMsg = resolve(WhatsAppConversationMessageService::class)->createFromSentAPIMessage(
            $wapSendingMsg
        );

        $this->logInfo("New wapConversationMsg created");
        $this->logInfo("wapConversationMsg ID: {$wapConversationMsg->id}");
        $this->logInfo("wapConversationMsg hash: {$wapConversationMsg->hash}");

        $this->broadcastRealTimeMessage($wapConversationMsg, $wapSendingMsg->client_id);

        $this->logInfo('Finished execution.');
        return true;
    }


    private function broadcastRealTimeMessage($wapConversationMsg, int $clientId): void
    {
        try {
            resolve(WhatsAppConversationRealTimeHelper::class)->broadcastNewConversationMessage(
                $wapConversationMsg, $clientId
            );
        } catch (Throwable $e) {
            $this->logInfo("broadcastRealTimeMessage error: {$e}");
            report($e);
        }
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('WhatsAppMetaAPISentConversationMessageStoreJobErrors')->error((string) $e);
    }

    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('WhatsAppMetaAPISentConversationMessageStoreJobInfo');
    }

}
