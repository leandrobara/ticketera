<?php

namespace App\Services\API\Dispatchers;

use App\Models\User;
use App\Models\Client;
use App\Models\WapBot;
use App\Models\WhatsAppSending;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Collection;
use App\Models\WhatsAppSendingMessage;
use App\Services\Traits\CustomDispatch;
use App\Models\WhatsAppMetaAPIConnection;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Jobs\WhatsAppEvents\SendWAPIMessageJob;
use App\Models\MongoDB\WapBot\WapBotConversation;
use App\Jobs\WhatsAppEvents\SendWAPSenderMessageJob;
use App\Jobs\WhatsAppEvents\FinishWAPISendingIfEndedJob;
use App\Jobs\WhatsAppEvents\WapBotSendFollowUpMessageJob;
use App\Jobs\WhatsAppEvents\ApplyWAutomationAfterSendJob;
use App\Jobs\WhatsAppEvents\SendWhatsAppMetaAPIMessageJob;
use App\Jobs\WhatsAppEvents\SendWAutomationWAPIMessageJob;
use App\Jobs\WhatsAppEvents\WapBotAnswerIncomingMessageJob;
use App\Jobs\WhatsAppEvents\WhatsAppMetaAPICloneTemplateJob;
use App\Jobs\WhatsAppEvents\FinishWAPSenderSendingIfEndedJob;
use App\Jobs\WhatsAppEvents\SendWAutomationWAPSenderMessageJob;
use App\Jobs\WhatsAppEvents\WhatsAppMetaAPISyncUsersTemplatesJob;
use App\Jobs\WhatsAppEvents\WapSalesAgentAnswerIncomingMessageJob;
use App\Jobs\WhatsAppEvents\CreateProposalAfterWAPIMessageSentJob;
use App\Jobs\WhatsAppEvents\FinishWhatsAppMetaAPISendingIfEndedJob;
use App\Jobs\WhatsAppEvents\SendWAutomationWhatsAppMetaAPIMessageJob;
use App\Jobs\WhatsAppEvents\ProcessWhatsAppMetaAPISentMessageStatusJob;
use App\Jobs\WhatsAppEvents\WhatsAppMetaAPIWebhookSentMessageStatusJob;
use App\Jobs\WhatsAppEvents\ApplyWAutomationProposalModifyLeadAfterSendJob;
use App\Jobs\WhatsAppEvents\WhatsAppMetaAPIWebhookConversationFileStoreJob;
use App\Jobs\WhatsAppEvents\WhatsAppMetaAPISentConversationMessageStoreJob;
use App\Jobs\WhatsAppEvents\WhatsAppMetaAPIWebhookConversationMessageStoreJob;
use App\Jobs\WhatsAppEvents\WapBotCreateSeedConversationFromOutgoingMessageJob;
use App\Jobs\WhatsAppEvents\WapBotCreateSeedConversationFromMetaAPISendJob;
use App\Jobs\WhatsAppEvents\WapBotSentConversationMessageStoreJob;
use App\Jobs\WhatsAppEvents\SendWhatsAppMetaAPINonLeadMessageJob;


class WhatsAppEventsDispatcherService
{

    use CustomDispatch;

    private $eventQueueName;
    private $queueConnection;


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function dispatchSendWAPIMessagesJobs(WhatsAppSending $whatsAppSending): void
    {
        $delaySecs = 5;
        foreach ($whatsAppSending->whatsAppSendingMessages as $i => $wapSendingMsg) {
            $this->dispatchSendWAPIMessageJob($wapSendingMsg, $delaySecs);
            $nextMsgDelaySecs = (int) mt_rand(13, 19);
            if ($i == 0) {
                // Entre el mensaje 1 y (si hay) mensaje 2, dejo pasar más, para prever posible cold start de WAPI
                $nextMsgDelaySecs = 50;
            }
            $delaySecs = $delaySecs + $nextMsgDelaySecs;
        }
    }


    public function dispatchSendWAPIMessageJob(WhatsAppSendingMessage $wapSendingMsg, int $delaySecs = 0): void
    {
        $params = [
            $wapSendingMsg->id,
            $wapSendingMsg->client_id,
            $wapSendingMsg->user_id,
            $wapSendingMsg->user->wapi_session_phone_number,
        ];
        $this->doCustomDispatch(
            SendWAPIMessageJob::class, $params, $delaySecs, $wapSendingMsg->client_id
        );
    }


    public function dispatchSendWAutomationWAPIMessagesJobs(WhatsAppSending $whatsAppSending): void
    {
        $delaySecs = 15;
        foreach ($whatsAppSending->whatsAppSendingMessages as $i => $wapSendingMsg) {
            $this->dispatchSendWAutomationWAPIMessageJob($wapSendingMsg, $delaySecs);
            // Entre el mensaje 1 y (si hay) mensaje 2, dejo pasar más tiempo, para prever posible cold start de WAPI.
            $nextMsgDelaySecs = $i == 0 ? 40 : 15;
            $delaySecs = $delaySecs + $nextMsgDelaySecs;
        }
    }
    
    
    public function dispatchSendWAutomationWAPIMessageJob(
        WhatsAppSendingMessage $wapSendingMsg,
        int $delaySecs = 0
    ): void {
        // Los WAPI de automations, los paso a una queue distinta, exclusiva.
        $eventQueueName = config('queue.whatsapp_automation_events');
        $params = [
            $wapSendingMsg->id,
            $wapSendingMsg->client_id,
            $wapSendingMsg->user_id,
            $wapSendingMsg->user->wapi_session_phone_number,
        ];
        $this->doCustomDispatch(
            SendWAutomationWAPIMessageJob::class, $params, $delaySecs, $wapSendingMsg->client_id, $eventQueueName
        );
    }


    public function dispatchApplyWAutomationAfterSendJob(WhatsAppSendingMessage $wapSendingMsg)
    {
        $jobClassName = ApplyWAutomationAfterSendJob::class;
        $this->doCustomDispatch($jobClassName, [$wapSendingMsg->id], null, $wapSendingMsg->client_id);
    }


    public function dispatchFinishWAPISendingIfEndedJob(int $wapSendingId, int $clientId, int $delaySecs = 0): void
    {
        $this->doCustomDispatch(FinishWAPISendingIfEndedJob::class, [$wapSendingId], $delaySecs, $clientId);
    }

    public function dispatchFinishWAPSenderSendingIfEndedJob(int $wapSendingId, int $clientId, int $delaySecs = 0): void
    {
        $this->doCustomDispatch(FinishWAPSenderSendingIfEndedJob::class, [$wapSendingId], $delaySecs, $clientId);
    }

    public function dispatchFinishWhatsAppMetaAPISendingIfEndedJob(
        int $wapSendingId,
        int $clientId,
        int $delaySecs = 0
    ): void {
        $this->doCustomDispatch(FinishWhatsAppMetaAPISendingIfEndedJob::class, [$wapSendingId], $delaySecs, $clientId);
    }


    public function dispatchCreateProposalAfterWAPIMessageSentJob(
        WhatsAppSendingMessage $wapSendingMsg,
        int $delaySecs = 0
    ): void {
        $this->doCustomDispatch(
            CreateProposalAfterWAPIMessageSentJob::class, [$wapSendingMsg->id], $delaySecs, $wapSendingMsg->client_id
        );
    }

    public function dispatchApplyWAutomationProposalModifyLeadAfterSendJob(
        WhatsAppSendingMessage $wapSendingMsg,
        int $delaySecs = 0
    ): void {
        $className = ApplyWAutomationProposalModifyLeadAfterSendJob::class;
        $this->doCustomDispatch($className, [$wapSendingMsg->id], $delaySecs, $wapSendingMsg->client_id);
    }




    // --------------------------------------
    // WAP SENDER CON PUSHER
    // --------------------------------------

    public function dispatchSendWAPSenderMessagesJobsBySending(WhatsAppSending $whatsAppSending): void
    {
        $this->dispatchMultipleSendWAPSenderMessagesJobs($whatsAppSending->whatsAppSendingMessages);
    }


    public function dispatchMultipleSendWAPSenderMessagesJobs(Collection $whatsAppSendingMessages): void
    {
        $delaySecs = 5;
        foreach ($whatsAppSendingMessages as $i => $wapSendingMsg) {
            $this->dispatchSendWAPSenderMessageJob($wapSendingMsg, $delaySecs);
            $nextMsgDelaySecs = (int) mt_rand(13, 19);
            $delaySecs = $delaySecs + $nextMsgDelaySecs;
        }
    }


    public function dispatchSendWAPSenderMessageJob(WhatsAppSendingMessage $wapSendingMsg, int $delaySecs = 0): void
    {
        $eventQueueName = config('queue.wap_sender_events');
        $params = [
            $wapSendingMsg->id,
            $wapSendingMsg->client_id,
            $wapSendingMsg->user_id,
            $wapSendingMsg->user->wap_sender_session_phone_number,
        ];
        $this->doCustomDispatch(
            SendWAPSenderMessageJob::class, $params, $delaySecs, $wapSendingMsg->client_id, $eventQueueName
        );
    }


    public function dispatchSendWAutomationWAPSenderMessagesJobs(WhatsAppSending $whatsAppSending): void
    {
        $delaySecs = 15;
        foreach ($whatsAppSending->whatsAppSendingMessages as $i => $wapSendingMsg) {
            $this->dispatchSendWAutomationWAPSenderMessageJob($wapSendingMsg, $delaySecs);
            $delaySecs = $delaySecs + 15;
        }
    }
    
    
    public function dispatchSendWAutomationWAPSenderMessageJob(
        WhatsAppSendingMessage $wapSendingMsg,
        int $delaySecs = 0
    ): void {
        $eventQueueName = config('queue.wap_sender_automation_events');
        $params = [
            $wapSendingMsg->id,
            $wapSendingMsg->client_id,
            $wapSendingMsg->user_id,
            $wapSendingMsg->user->wap_sender_session_phone_number,
        ];
        $this->doCustomDispatch(
            SendWAutomationWAPSenderMessageJob::class, $params, $delaySecs, $wapSendingMsg->client_id, $eventQueueName
        );
    }


    // --------------------------------------
    // WHATSAPP META API
    // --------------------------------------
    public function dispatchSendWhatsAppMetaAPIMessagesJobs(WhatsAppSending $whatsAppSending): void
    {
        $delaySecs = 1;
        foreach ($whatsAppSending->whatsAppSendingMessages as $i => $wapSendingMsg) {
            $this->dispatchSendWhatsAppMetaAPIMessageJob($wapSendingMsg, $delaySecs);
            $nextMsgDelaySecs = 1;
            if ($i == 0) {
                $nextMsgDelaySecs = 1;
            }
            $delaySecs = $delaySecs + $nextMsgDelaySecs;
        }
    }


    public function dispatchSendWhatsAppMetaAPIMessageJob(
        WhatsAppSendingMessage $wapSendingMsg,
        int $delaySecs = 0
    ): void {
        $eventQueueName = config('queue.whatsapp_meta_api_sender_events');
        $params = [
            $wapSendingMsg->user->id,
            $wapSendingMsg->client_id,
            $wapSendingMsg->id,
            $wapSendingMsg->user->whatsAppMetaAPIConnection->id,
        ];
        $this->doCustomDispatch(
            SendWhatsAppMetaAPIMessageJob::class, $params, $delaySecs, $wapSendingMsg->client_id, $eventQueueName
        );
    }


    public function dispatchSendWhatsAppMetaAPIMessagesJobsBySending(WhatsAppSending $whatsAppSending): void
    {
        $this->dispatchMultipleSendWhatsAppMetaAPIMessagesJobs($whatsAppSending->whatsAppSendingMessages);
    }


    public function dispatchMultipleSendWhatsAppMetaAPIMessagesJobs(Collection $whatsAppSendingMessages): void
    {
        $delaySecs = 5;
        foreach ($whatsAppSendingMessages as $i => $wapSendingMsg) {
            $this->dispatchSendWhatsAppMetaAPIMessageJob($wapSendingMsg, $delaySecs);
            $nextMsgDelaySecs = (int) mt_rand(1, 1);
            $delaySecs = $delaySecs + $nextMsgDelaySecs;
        }
    }


    public function dispatchSendWAutomationWhatsAppMetaAPIMessagesJobs(WhatsAppSending $whatsAppSending): void
    {
        $delaySecs = 1;
        foreach ($whatsAppSending->whatsAppSendingMessages as $i => $wapSendingMsg) {
            $this->dispatchSendWAutomationWhatsAppMetaAPIMessageJob($wapSendingMsg, $delaySecs);
            $delaySecs = $delaySecs + 1;
        }
    }
    
    
    public function dispatchSendWAutomationWhatsAppMetaAPIMessageJob(
        WhatsAppSendingMessage $wapSendingMsg,
        int $delaySecs = 0
    ): void {
        $eventQueueName = config('queue.whatsapp_meta_api_wautomation_queue');

        $params = [
            $wapSendingMsg->user_id,
            $wapSendingMsg->client_id,
            $wapSendingMsg->id,
            $wapSendingMsg->user->whatsAppMetaAPIConnection->id,
        ];
        $jobClassName = SendWAutomationWhatsAppMetaAPIMessageJob::class;
        $this->doCustomDispatch($jobClassName, $params, $delaySecs, $wapSendingMsg->client_id, $eventQueueName);
    }


    public function dispatchWhatsAppMetaAPIWebhookSentMessageStatusJob(array $metaWebhookPayload): void
    {
        $delaySecs = 0;
        $clientId = null;
        $params = [$metaWebhookPayload];
        $eventQueueName = config('queue.whatsapp_meta_api_webhook_queue');
        $this->doCustomDispatch(
            WhatsAppMetaAPIWebhookSentMessageStatusJob::class, $params, $delaySecs, $clientId, $eventQueueName
        );
    }


    /**
     * $triggerUser -> es el user que disparó la acción
     * $triggerAction -> templateCreate | templateDelete | userWabaSync
     */
    public function dispatchWhatsAppMetaAPISyncUsersTemplatesJob(
        User $triggerUser,
        string $triggerAction,
        ?WhatsAppTemplate $whatsAppTemplate = null,
    ): void {
        $delaySecs = 0;
        $params = [$triggerUser->id, $triggerAction, $whatsAppTemplate?->id];
        $eventQueueName = config('queue.whatsapp_meta_api_templates_sync_queue');
        $this->doCustomDispatch(
            WhatsAppMetaAPISyncUsersTemplatesJob::class, $params, $delaySecs, $triggerUser->client_id, $eventQueueName
        );
    }


    public function dispatchWhatsAppMetaAPICloneTemplateJob(
        WhatsAppTemplate $sourceWhatsAppTemplate,
        WhatsAppMetaAPIConnection $targetWapMetaConn,
    ): void {
        $delaySecs = 0;
        $params = [$sourceWhatsAppTemplate->id, $targetWapMetaConn->id];
        $eventQueueName = config('queue.whatsapp_meta_api_clone_template_queue');
        $this->doCustomDispatch(
            WhatsAppMetaAPICloneTemplateJob::class, $params, $delaySecs, $targetWapMetaConn->client_id, $eventQueueName
        );
    }


    public function dispatchWapBotAnswerIncomingMessageJob(array $metaWebhookPayload): void
    {
        $delaySecs = 0;
        $clientId = null;
        $params = [$metaWebhookPayload];
        $eventQueueName = config('queue.wap_bot_queue');
        $this->doCustomDispatch(
            WapBotAnswerIncomingMessageJob::class, $params, $delaySecs, $clientId, $eventQueueName
        );
    }
    

    public function dispatchWapBotSendFollowUpMessageJob(
        WapBot $wapBot,
        WapBotConversation $wapBotConversation
    ): void {
        $delaySecs = 0;
        $clientId = null;
        $params = [$wapBot->id, $wapBotConversation->id];
        $eventQueueName = config('queue.wap_bot_queue');
        $this->doCustomDispatch(WapBotSendFollowUpMessageJob::class, $params, $delaySecs, $clientId, $eventQueueName);
    }
    

    public function dispatchWapBotCreateSeedConversationFromOutgoingMessageJob(array $metaWebhookPayload): void
    {
        $delaySecs = 0;
        $clientId = null;
        $params = [$metaWebhookPayload];
        $eventQueueName = config('queue.wap_bot_queue');
        $this->doCustomDispatch(
            WapBotCreateSeedConversationFromOutgoingMessageJob::class, $params, $delaySecs, $clientId, $eventQueueName
        );
    }


    public function dispatchWapBotCreateSeedConversationFromMetaAPISendJob(
        string $customerPhoneNumber,
        string $botMetaPhoneNumberId,
        string $botPhoneNumber,
    ): void {
        $delaySecs = 0;
        $clientId = null;
        $params = [$customerPhoneNumber, $botMetaPhoneNumberId, $botPhoneNumber];
        $eventQueueName = config('queue.wap_bot_queue');
        $this->doCustomDispatch(
            WapBotCreateSeedConversationFromMetaAPISendJob::class, $params, $delaySecs, $clientId, $eventQueueName
        );
    }


    public function dispatchWapSalesAgentAnswerIncomingMessageJob(array $metaWebhookPayload): void
    {
        $delaySecs = 0;
        $clientId = null;
        $params = [$metaWebhookPayload];
        $eventQueueName = config('queue.wap_sales_agent_queue');
        $this->doCustomDispatch(
            WapSalesAgentAnswerIncomingMessageJob::class, $params, $delaySecs, $clientId, $eventQueueName
        );
    }


    public function dispatchWhatsAppMetaAPIWebhookConversationMessageStoreJob(array $metaWebhookPayload): void
    {
        $delaySecs = 0;
        $clientId = null;
        $params = [$metaWebhookPayload];
        $eventQueueName = config('queue.whatsapp_meta_api_webhook_queue');
        $this->doCustomDispatch(
            WhatsAppMetaAPIWebhookConversationMessageStoreJob::class, $params, $delaySecs, $clientId, $eventQueueName
        );
    }


    public function dispatchWhatsAppMetaAPIWebhookConversationFileStoreJob(string $whatsAppConversationMessageId): void
    {
        $delaySecs = 0;
        $clientId = null;
        $params = [$whatsAppConversationMessageId];
        $eventQueueName = config('queue.whatsapp_meta_api_webhook_queue');
        $this->doCustomDispatch(
            WhatsAppMetaAPIWebhookConversationFileStoreJob::class, $params, $delaySecs, $clientId, $eventQueueName
        );
    }


    public function dispatchWapBotSentConversationMessageStoreJob(array $messageDataArr): void
    {
        $params = [$messageDataArr];
        $eventQueueName = config('queue.whatsapp_meta_api_webhook_queue');
        $this->doCustomDispatch(
            WapBotSentConversationMessageStoreJob::class, $params, 0, null, $eventQueueName
        );
    }


    public function dispatchWhatsAppMetaAPISentConversationMessageStoreJob(
        int $whatsAppSendingMessageId,
        int $delaySecs = 0
    ): void {
        $clientId = null;
        $params = [$whatsAppSendingMessageId];
        $eventQueueName = config('queue.whatsapp_meta_api_webhook_queue');
        $this->doCustomDispatch(
            WhatsAppMetaAPISentConversationMessageStoreJob::class, $params, $delaySecs, $clientId, $eventQueueName
        );
    }


    public function dispatchSendWhatsAppMetaAPINonLeadMessageJob(
        int $userId,
        int $clientId,
        string $customerPhoneNumber,
        int $whatsAppMetaAPIConnectionId,
        ?string $chatMessage = null,
        ?int $whatsAppTemplateId = null,
        array $bodyVariables = [],
        array $headerVariables = [],
        ?array $conversationMessageMedia = null,
        int $delaySecs = 0,
    ): void {
        $params = [
            $userId,
            $clientId,
            $customerPhoneNumber,
            $whatsAppMetaAPIConnectionId,
            $chatMessage,
            $whatsAppTemplateId,
            $bodyVariables,
            $headerVariables,
            $conversationMessageMedia,
        ];
        $eventQueueName = config('queue.whatsapp_meta_api_non_lead_sender_queue');
        $this->doCustomDispatch(
            SendWhatsAppMetaAPINonLeadMessageJob::class, $params, $delaySecs, $clientId, $eventQueueName
        );
    }

}
