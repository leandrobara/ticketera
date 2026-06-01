<?php

namespace App\Jobs\WhatsAppEvents;

use Exception;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\API\WapBot\WapBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\DTO\WapBot\WhatsAppMetaAPIWebhookPayloadDTO;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Services\API\WapBot\WapBotConversationService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;


/**
 * queue: ENV_wap_bot_queue
 */
class WapBotCreateSeedConversationFromOutgoingMessageJob implements ShouldQueue
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
        $this->logInfo('Starting WapBotCreateSeedConversationFromOutgoingMessageJob');
        $this->logInfo(json_encode($this->metaWebhookPayload));

        $payloadDTO = new WhatsAppMetaAPIWebhookPayloadDTO($this->metaWebhookPayload);
        if (!$payloadDTO->isOutgoingEchoMessage()) {
            $this->logInfo('Payload is not an outgoing echo message. RETURNING.');
            return true;
        }

        $customerPhoneNumber = $payloadDTO->getToNumber();
        $clientyUserPhoneNumber = $payloadDTO->getFromNumber();
        $connectedPhoneNumberId = $payloadDTO->getPhoneNumberId();
        $receivedMetaMessageTimestamp = $payloadDTO->getTimestamp();
        
        if (!$connectedPhoneNumberId) {
            $this->logInfo('Missing phone identifiers in payload. RETURNING.');
            return true;
        }

        $wapBotService = resolve(WapBotService::class);
        $wapBot = $wapBotService->findActiveByMetaPhoneNumberId($connectedPhoneNumberId);
        if (!$wapBot) {
            $this->logInfo('No active WapBot. RETURNING.');
            return true;
        }
        $this->logInfo("wapBotId: {$wapBot->id}");
        $this->logInfo("clientId: {$wapBot->client_id}");

        if (!$wapBot->client || !$wapBot->client->enabled) {
            $this->logInfo('Client missing or disabled. RETURNING.');
            return true;
        }

        // $whatsAppMetaAPIService = resolve(WhatsAppMetaAPIService::class);
        // $whatsAppConnection = $whatsAppMetaAPIService->findActiveConnection(
        //     $wapBot->client, $connectedPhoneNumberId
        // );
        // if (!$whatsAppConnection) {
        //     $this->logInfo('No WhatsAppMetaAPIConnection. RETURNING.');
        //     return true;
        // }
        // $this->logInfo("whatsAppConnectionId: {$whatsAppConnection->id}");

        $conversationService = resolve(WapBotConversationService::class);
        $conversation = $conversationService->findLatestConversation(
            clientId: $wapBot->client_id,
            customerPhoneNumber: $customerPhoneNumber,
            botMetaPhoneNumberId: $connectedPhoneNumberId,
        );
        $this->logInfo("conversationId: {$conversation?->id}");

        if ($conversation) {
            // La conversación ya existe. Registramos que el vendedor le escribió manualmente al customer
            $conversationService->updateLastSentMessageToCustomerAt($conversation);
            $this->logInfo('WapBot conversation already exists. Updated lastSentMessageToCustomerAt. RETURNING.');
            return true;
        }

        $seedConversation = $conversationService->createOutgoingMessageSeedConversation($wapBot, $payloadDTO);
        $this->logInfo("Created new seed conversation. ID: {$seedConversation->id}");
        return true;
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('WapBotCreateSeedConversationFromOutgoingMessageJobErrors')->error((string) $e);
    }

    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('WapBotCreateSeedConversationFromOutgoingMessageJobInfo');
    }


}
