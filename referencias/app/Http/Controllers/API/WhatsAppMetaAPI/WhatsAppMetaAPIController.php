<?php

namespace App\Http\Controllers\API\WhatsAppMetaAPI;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Models\WhatsAppSending;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\WhatsAppMetaAPIConnection;
use App\Services\API\WapBot\WapBotService;
use App\Services\API\WhatsAppSendingService;
use App\Http\Resources\WhatsAppSendingResource;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WapBot\WapBotConversationService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppNonLeadMediaService;
use App\Http\Requests\WhatsAppMetaAPI\HandleOAuthCallbackRequest;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
use App\Http\Requests\WhatsAppMetaAPI\SaveSelectedConnectionRequest;
use App\Http\Requests\WhatsAppMetaAPI\WhatsAppMetaAPISendMessageRequest;
use App\Http\Requests\WhatsAppMetaAPI\WhatsAppMetaAPIUnsubscribeRequest;
use App\Http\Requests\WhatsAppMetaAPI\WhatsAppMetaAPICancelSendingRequest;
use App\Http\Requests\WhatsAppMetaAPI\WhatsAppMetaAPISendOpenMessageRequest;
use App\Http\Requests\WhatsAppMetaAPI\WhatsAppMetaAPIScheduleMessageRequest;
use App\Http\Requests\WhatsAppMetaAPI\WhatsAppMetaAPIValidateWebhookRequest;
use App\Http\Requests\WhatsAppMetaAPI\WhatsAppMetaAPICloneConnectionRequest;
use App\Http\Requests\WhatsAppMetaAPI\WhatsAppMetaAPIHandleNewWebhookRequest;
use App\Http\Requests\WhatsAppMetaAPI\WhatsAppMetaAPISendNonLeadMessageRequest;


class WhatsAppMetaAPIController extends BaseAPIController
{

    public function getPopulatedConnection(Request $req): array
    {
        $wabaPopulatedConnDTO = resolve(WhatsAppMetaAPIService::class)->findPopulatedLastConnectionDTOByUser(
            $req->user
        );
        return $this->getSuccessResponse($wabaPopulatedConnDTO);
    }


    public function associatePhoneNumberToConnection(
        WhatsAppMetaAPIConnection $whatsAppMetaConnection,
        SaveSelectedConnectionRequest $req
    ): array {
        $wapMetaAPIService = resolve(WhatsAppMetaAPIService::class);
        
        $wapMetaAPIService->susbcribeWABAToWebhooks($whatsAppMetaConnection, $req->meta_waba_id);
        
        $wabaAssociatedConn = $wapMetaAPIService->associatePhoneNumberToConnection(
            metaWABAId: $req->meta_waba_id,
            metaPhoneNumberId: $req->meta_phone_number_id,
            whatsAppMetaConnection: $whatsAppMetaConnection,
        );

        resolve(WhatsAppEventsDispatcherService::class)->dispatchWhatsAppMetaAPISyncUsersTemplatesJob(
            triggerUser: $req->user, triggerAction: 'userWabaSync'
        );

        return $this->getSuccessResponse($wabaAssociatedConn);
    }


    public function cloneConnection(
        WhatsAppMetaAPIConnection $sourceConnection,
        WhatsAppMetaAPICloneConnectionRequest $req
    ): array {
        $clonedConnection = resolve(WhatsAppMetaAPIService::class)->cloneConnection(
            sourceConnection: $sourceConnection, targetUser: $req->user
        );
        return $this->getSuccessResponse($clonedConnection);
    }


    public function unsubscribe(WhatsAppMetaAPIUnsubscribeRequest $req): array
    {
        $deletedConnection = resolve(WhatsAppMetaAPIService::class)->deleteUserConnection($req->user);
        return $this->getSuccessResponse($deletedConnection);
    }


    // Devuelve la URL para vincular con Meta.
    public function getOAuthRedirectUrl(Request $req): array
    {
        $oAuthRedirectUrl = resolve(WhatsAppMetaAPIService::class)->getOAuthRedirectUrl($req->user);
        return $this->getSuccessResponse($oAuthRedirectUrl);
    }


    public function handleOAuthCallback(HandleOAuthCallbackRequest $req)
    {
        $code = $req->getCode();
        $user = $req->getStateUser();
        $client = $req->getStateClient();
        $wapMetaAPIService = resolve(WhatsAppMetaAPIService::class);

        $accessToken = $wapMetaAPIService->exchangeCodeForAccessToken($code);
        $wapMetaConnection = $wapMetaAPIService->createOrUpdateNewUserConnection($user, $accessToken);

        if ($wapMetaConnection->waba_id) {
            resolve(WhatsAppEventsDispatcherService::class)->dispatchWhatsAppMetaAPISyncUsersTemplatesJob(
                triggerUser: $user, triggerAction: 'userWabaSync'
            );
        }

        // {subdomain}.clienty.co/configurations/whatsapp-meta-api
        $redirectUrl = config('app.facebook.waba_client_finish_url');
        $redirectUrl = str_replace('{subdomain}', $client->subdomain, $redirectUrl);
        return redirect($redirectUrl);
    }


    // Registra log en WhatsAppMetaAPIControllerInfo.log
    public function validateWebhook(WhatsAppMetaAPIValidateWebhookRequest $req)
    {
        return resolve(WhatsAppMetaAPIService::class)->validateWebhook($req->validated());
    }


    public function sendMessage(WhatsAppTemplate $whatsAppTemplate, WhatsAppMetaAPISendMessageRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppMetaAPIService::class)->createNewSending(
            $whatsAppTemplate, $req->dto()
        );
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function sendOpenMessage(WhatsAppMetaAPISendOpenMessageRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppMetaAPIService::class)->createNewOpenMessageSending($req->dto());
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function sendNonLeadMessage(WhatsAppMetaAPISendNonLeadMessageRequest $req)
    {
        $conversationMessageMedia = null;
        $connection = $req->user->whatsAppMetaAPIConnection;
        $mediaService = resolve(WhatsAppNonLeadMediaService::class);

        if ($req->file('audioVoiceFile')) {
            $conversationMessageMedia = $mediaService->processAudioVoiceFile(
                connection: $connection,
                normalizedPhone: $req->normalizedPhone,
                audioVoiceFile: $req->file('audioVoiceFile'),
            );
        }
        if ($req->file('mediaFile')) {
            $conversationMessageMedia = $mediaService->processMediaFile(
                connection: $connection,
                mediaType: $req->mediaFileType,
                mediaFile: $req->file('mediaFile'),
                normalizedPhone: $req->normalizedPhone,
            );
        }

        resolve(WhatsAppEventsDispatcherService::class)->dispatchSendWhatsAppMetaAPINonLeadMessageJob(
            userId: $req->user->id,
            clientId: $req->user->client_id,
            chatMessage: $req->input('chatMessage'),
            customerPhoneNumber: $req->normalizedPhone,
            bodyVariables: $req->resolvedBodyVariables,
            headerVariables: $req->resolvedHeaderVariables,
            whatsAppTemplateId: $req->whatsAppTemplate?->id,
            conversationMessageMedia: $conversationMessageMedia,
            whatsAppMetaAPIConnectionId: (int) $req->input('whatsAppMetaAPIConnectionId'),
        );
        return $this->getSuccessResponse(true);
    }


    public function scheduleMessage(WhatsAppTemplate $whatsAppTemplate, WhatsAppMetaAPIScheduleMessageRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppMetaAPIService::class)->createNewSending(
            $whatsAppTemplate, $req->dto()
        );
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function cancelSending(WhatsAppSending $whatsAppSending, WhatsAppMetaAPICancelSendingRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppSendingService::class)->cancel($whatsAppSending);
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    /**
     * @todo VALIDAR SIGNATURE DE META!
     * WhatsAppMetaAPIHandleNewWebhookRequest Guarda log en WhatsAppMetaAPIControllerInfo.log
     */
    public function handleNewWebhook(WhatsAppMetaAPIHandleNewWebhookRequest $req)
    {
        $payload = $req->all();
        $logChannel = Log::channel('WhatsAppMetaAPIControllerInfo');
        
        $FFPhoneNumber = '5491159711575';
        $clientyFFNumber = '5491126787252';
        $baraPhoneNumber = '5491168561237';
        
        $field = Arr::get($payload, 'entry.0.changes.0.field');
        $fromPhoneNumber = Arr::get($payload, 'entry.0.changes.0.value.messages.0.from');
        $echoToPhoneNumber = Arr::get($payload, 'entry.0.changes.0.value.message_echoes.0.to');
        $echoFromPhoneNumber = Arr::get($payload, 'entry.0.changes.0.value.message_echoes.0.from');
        $connectedPhoneNumberId = Arr::get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');
        $connectedPhoneNumber = Arr::get($payload, 'entry.0.changes.0.value.metadata.display_phone_number');

        $isFromFFPhone = $fromPhoneNumber == $FFPhoneNumber;
        $isFromFerPhone = $fromPhoneNumber == '5492604407509';
        $isFromDaniPhone = $fromPhoneNumber == '5491131869868';
        $isFromRocioPhone = $fromPhoneNumber == '5492215026565';
        $isFromJuanoPhone = $fromPhoneNumber == '5491138258411';
        $isFromBaraPhone = $fromPhoneNumber == $baraPhoneNumber;
        $isFromBrunoPhone = $fromPhoneNumber == '5491170641153';
        $isFromTinchoPhone = $fromPhoneNumber == '5491130871926';
        $isFromClientyFFPhone = $fromPhoneNumber == $clientyFFNumber;
        $isToNamatoWapBot = $connectedPhoneNumberId == '459303777264259';
        $isToMktSimpleWapBot = $connectedPhoneNumberId == '514800978383467';
        $isToClientyNotifNumber = $connectedPhoneNumberId == '247733821746167';
        $isEchoFromClientyFFSentMessage = $echoFromPhoneNumber == $clientyFFNumber;
        // +1 318 706-2664 (Kapso) (meta phone ID: 976199978920754)
        $isToKapsoSalesAgentBot = $connectedPhoneNumberId == config('app.kapso.wap_sales_agent_meta_phone_id');

        // @todo VALIDAR SIGNATURE DE META!
        $wapDispatcher = resolve(WhatsAppEventsDispatcherService::class);

        // Guardo el mensaje en MongoDB, en el collection "WhatsAppConversationMessages"
        if (!$req->isStatusChangeMessage()) {
            $wapDispatcher->dispatchWhatsAppMetaAPIWebhookConversationMessageStoreJob($payload);
            $logChannel->info('whatsAppMetaAPIWebhookConversationMessageStoreJob dispatched. RETURNING.');
        }

        // WAP SALES AGENT
        if ($req->isIncomingMessage() && $isToKapsoSalesAgentBot) {
            // NO DESCOMENTAR en DEV/LOCAL!
            // $devUrl = 'https://c316-190-55-206-195.ngrok-free.app/api/whatsapp-meta-api/webhook';
            // $logChannel->info("Enviando a FF DEV ({$devUrl}).");
            // Http::withHeaders(['Content-Type' => 'application/json'])->post($devUrl, $payload);
            // $logChannel->info("Webhook redirigido a DEV ({$devUrl}). RETURNING SUCCESS TRUE.");

            $wapDispatcher->dispatchWapSalesAgentAnswerIncomingMessageJob($payload);
            $logChannel->info('WapSalesAgentAnswerIncomingMessageJob dispatched. RETURNING.');
            return $this->getSuccessResponse(true);
        }
    


        // Envía todo lo que sale y llega de FF Clienty Notificaciones
        // if ($isToClientyNotifNumber) {
        //     $devUrl = 'https://c316-190-55-206-195.ngrok-free.app/api/whatsapp-meta-api/webhook';
        //     $logChannel->info("Enviando a FF DEV ({$devUrl}).");
        //     Http::withHeaders(['Content-Type' => 'application/json'])->post($devUrl, $payload);
        //     $logChannel->info("Webhook redirigido a DEV ({$devUrl}). RETURNING SUCCESS TRUE.");
        // }


        if ($req->isStatusChangeMessage()) {
            $wapDispatcher->dispatchWhatsAppMetaAPIWebhookSentMessageStatusJob($payload);
            $logChannel->info('WhatsAppMetaAPIWebhookSentMessageStatusJob dispatched. RETURNING.');
            return $this->getSuccessResponse(true);
        }

        if ($req->isIncomingMessage()) {
            $wapDispatcher->dispatchWapBotAnswerIncomingMessageJob($payload);
            $logChannel->info('WapBotAnswerIncomingMessageJob dispatched. RETURNING.');
            return $this->getSuccessResponse(true);
        }

        if ($req->isOutgoingEchoMessage()) {
            $wapDispatcher->dispatchWapBotCreateSeedConversationFromOutgoingMessageJob($payload);
            $logChannel->info('WapBotCreateSeedConversationFromOutgoingMessageJob dispatched. RETURNING.');
            return $this->getSuccessResponse(true);
        }

        return $this->getSuccessResponse(true);
    }

}
