<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\WhatsAppMetaAPI\WhatsAppConversationMessageService;
use App\Helpers\WhatsAppMetaAPI\WhatsAppConversationRealTimeHelper;


/**
 * queue: ENV_whatsapp_meta_api_webhook_queue
 */
class WapBotSentConversationMessageStoreJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;

    protected $logUuid = null;


    public function __construct(
        public readonly array $messageData,
    ) {
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        $this->logInfo('Starting WapBotSentConversationMessageStoreJob');
        $this->logInfo('messageData: ' . json_encode($this->messageData));

        $requiredKeys = [
            'wapBotId',
            'clientId',
            'messageType',
            'messageText',
            'customerPhoneNumber',
            'connectionPhoneNumberId',
        ];
        foreach ($requiredKeys as $key) {
            if (empty($this->messageData[$key])) {
                $this->logInfo("Missing required key: {$key}. RETURNING.");
                return true;
            }
        }

        $wapConversationMsg = resolve(WhatsAppConversationMessageService::class)->createFromWapBotMessage(
            $this->messageData
        );

        $this->logInfo("New wapConversationMsg created");
        $this->logInfo("wapConversationMsg ID: {$wapConversationMsg->id}");
        $this->logInfo("wapConversationMsg hash: {$wapConversationMsg->hash}");

        $this->broadcastRealTimeMessage($wapConversationMsg, $this->messageData['clientId']);

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
        Log::channel('WapBotSentConversationMessageStoreJobErrors')->error((string) $e);
    }

    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('WapBotSentConversationMessageStoreJobInfo');
    }

}
