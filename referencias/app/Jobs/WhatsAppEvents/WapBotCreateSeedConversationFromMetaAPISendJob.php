<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\API\WapBot\WapBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\WapBot\WapBotConversationService;


/**
 * queue: ENV_wap_bot_queue
 */
class WapBotCreateSeedConversationFromMetaAPISendJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;

    protected $logUuid = null;


    public function __construct(
        public readonly string $customerPhoneNumber,
        public readonly string $botMetaPhoneNumberId,
        public readonly string $botPhoneNumber,
    ) {
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        $this->logInfo('Starting WapBotCreateSeedConversationFromMetaAPISendJob');
        $this->logInfo("customerPhoneNumber: {$this->customerPhoneNumber}");
        $this->logInfo("botMetaPhoneNumberId: {$this->botMetaPhoneNumberId}");
        $this->logInfo("botPhoneNumber: {$this->botPhoneNumber}");

        $wapBotService = resolve(WapBotService::class);
        $wapBot = $wapBotService->findActiveByMetaPhoneNumberId($this->botMetaPhoneNumberId);
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

        $wapBotConversationService = resolve(WapBotConversationService::class);
        $conversation = $wapBotConversationService->findLatestConversation(
            clientId: $wapBot->client_id,
            customerPhoneNumber: $this->customerPhoneNumber,
            botMetaPhoneNumberId: $this->botMetaPhoneNumberId,
        );
        $this->logInfo("conversationId: {$conversation?->id}");

        if ($conversation) {
            $wapBotConversationService->updateLastSentMessageToCustomerAt($conversation);
            $this->logInfo('WapBot conversation already exists. Updated lastSentMessageToCustomerAt. RETURNING.');
            return true;
        }

        $seedConversation = $wapBotConversationService->createNewSeedConversation(
            wapBot: $wapBot,
            lastActivityAt: time(),
            botPhoneNumber: $this->botPhoneNumber,
            customerPhoneNumber: $this->customerPhoneNumber,
        );
        $this->logInfo("Created new seed conversation. ID: {$seedConversation->id}");
        return true;
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('WapBotCreateSeedConversationFromMetaAPISendJobErrors')->error((string) $e);
    }

    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('WapBotCreateSeedConversationFromMetaAPISendJobInfo');
    }


}
