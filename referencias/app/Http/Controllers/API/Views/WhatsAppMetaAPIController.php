<?php

namespace App\Http\Controllers\API\Views;

use Illuminate\Http\Request;
use App\Helpers\PhonesHelper;
use App\Models\LeadContactPhone;
use App\Models\WhatsAppMetaAPIConnection;
use App\Http\Controllers\API\BaseAPIController;
use App\DTO\WhatsAppMetaAPI\WhatsAppConversationMessageDTO;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Models\MongoDB\WhatsAppMetaAPI\WhatsAppConversationMessage;
use App\Services\API\WhatsAppMetaAPI\WhatsAppConversationMessageService;
use App\Http\Requests\Views\WhatsAppMetaAPI\WhatsAppMetaAPIMessageModalRequest;
use App\Http\Requests\Views\WhatsAppMetaAPI\WhatsAppMetaAPIListTemplatesRequest;
use App\Http\Resources\Views\WhatsAppMetaAPI\WhatsAppMetaAPIMessageModalResource;
use App\Http\Requests\Views\WhatsAppMetaAPI\WhatsAppMetaAPIListConnectionsRequest;
use App\Http\Requests\Views\WhatsAppMetaAPI\WhatsAppMetaAPIListConversationMessagesRequest;
use App\Http\Requests\WhatsAppMetaAPI\WhatsAppMetaAPIGetNonLeadSendMessageModalInfoRequest;
use App\Http\Requests\Views\WhatsAppMetaAPI\WhatsAppMetaAPIGetConversationMessageMediaUrlRequest;
use App\Http\Requests\WhatsAppMetaAPI\WhatsAppMetaAPIGetNonLeadSendMessageModalConversationInfoRequest;


class WhatsAppMetaAPIController extends BaseAPIController
{

    public function newMessageModal(WhatsAppMetaAPIMessageModalRequest $req)
    {
        $modalDTO = resolve(WhatsAppMetaAPIService::class)->getMessageModalInfo($req->validatedLeadIds());
        return $this->getSuccessResponse(new WhatsAppMetaAPIMessageModalResource($modalDTO));
    }


    public function listConnections(WhatsAppMetaAPIListConnectionsRequest $req)
    {
        $connections = resolve(WhatsAppMetaAPIService::class)->findConnectionsByClient(
            $req->client, ['with' => ['user']]
        );
        $permission = $req->client->clientSettings->whatsapp_meta_api_conversations_permission;
        $isOwnerRestricted = in_array($permission, ['owner_only', 'owner_leads_only']);
        if ($isOwnerRestricted || $req->user->type != 'admin') {
            $connections = $connections->where('user_id', $req->user->id)->values();
        }
        return $this->getSuccessResponse($connections);
    }


    public function listMetaTemplates(WhatsAppMetaAPIListTemplatesRequest $req)
    {
        if (!$req->user->whatsAppMetaAPIConnection) {
            return $this->getSuccessResponse([]);
        }
        $wapMetaTpls = resolve(WhatsAppMetaAPIService::class)->listTemplates($req->user->whatsAppMetaAPIConnection);
        return $this->getSuccessResponse($wapMetaTpls);
    }


    // @todo Refactorizar, encapsular lógica.
    // Se usa en el modal de envío de mensajes (a prospectos)
    public function getConversationWindowInfo(LeadContactPhone $leadContactPhone, Request $req)
    {
        // Si las conversaciones están bloqueadas, no mostrar conversación reciente (el envío sigue permitido)
        $permission = $req->client->clientSettings->whatsapp_meta_api_conversations_permission;
        if ($permission === 'none') {
            return $this->getSuccessResponse([
                'recentConversationMessages' => [], 'isConversationWindowOpen' => false,
            ]);
        }

        $connection = $req->user->whatsAppMetaAPIConnection;
        if (!$connection) {
            return $this->getSuccessResponse([
                'recentConversationMessages' => [], 'isConversationWindowOpen' => false,
            ]);
        }

        $formattedPhone = resolve(PhonesHelper::class)->getWhatsAppFormattedPhoneFromLeadContactPhone(
            $leadContactPhone, $req->client
        );
        $messages = resolve(WhatsAppConversationMessageService::class)->listConversation(
            $connection->phone_number_id, $formattedPhone, ['limit' => 10]
        );

        $recentConversationMessages = $messages
            ->map(fn ($msg) => (new WhatsAppConversationMessageDTO($msg))->toArray())
            ->values()
            ->toArray()
        ;

        $lastIncomingMsg = $messages->last(function ($msg) {
            return $msg->direction === WhatsAppConversationMessage::DIRECTION_INCOMING;
        });
        $isConversationWindowOpen = false;
        if ($lastIncomingMsg && $lastIncomingMsg->metaReceivedMessageTimestamp) {
            $diffMinutes = now()->diffInMinutes($lastIncomingMsg->metaReceivedMessageTimestamp, true);
            $isConversationWindowOpen = $diffMinutes < (24 * 59); //le robo unos minutos por seguridad
        }

        return $this->getSuccessResponse([
            'isConversationWindowOpen' => $isConversationWindowOpen,
            'recentConversationMessages' => $recentConversationMessages,
        ]);
    }


    public function listConversationMessages(
        string $customerPhoneNumber,
        WhatsAppMetaAPIConnection $whatsAppMetaAPIConnection,
        WhatsAppMetaAPIListConversationMessagesRequest $req
    ) {
        $formattedPhone = resolve(PhonesHelper::class)->formatPhoneForWhatsAppWithSettings(
            $customerPhoneNumber, $req->client->country_code, $req->client->clientSettings ?? null
        );

        $metaConnectedPhoneNumberId = $whatsAppMetaAPIConnection->phone_number_id;
        $messages = resolve(WhatsAppConversationMessageService::class)->listConversation(
            $metaConnectedPhoneNumberId, $formattedPhone, $req->validated()
        );
        $dtos = $messages->map(fn ($msg) => (new WhatsAppConversationMessageDTO($msg))->toArray());
        return $this->getSuccessResponse($dtos);
    }


    public function getConversationMessageMediaUrl(
        string $conversationMessageId,
        WhatsAppMetaAPIGetConversationMessageMediaUrlRequest $req
    ) {
        $service = resolve(WhatsAppConversationMessageService::class);
        $whatsAppConversationMessage = $service->findOneById($conversationMessageId);
        if (!$whatsAppConversationMessage) {
            return $this->getErrorResponse('Mensaje no encontrado');
        }
        $temporaryUrl = $service->getMediaTemporaryUrl($whatsAppConversationMessage);
        if (!$temporaryUrl) {
            return $this->getErrorResponse('El archivo de media no está disponible');
        }

        return $this->getSuccessResponse([
            'url' => $temporaryUrl,
            'mimeType' => $whatsAppConversationMessage->media['mime_type'] ?? 'application/octet-stream',
        ]);
    }


    // Las validaciones de conexión Meta API las hace el request.
    // Reutiliza getMessageModalInfo con leadIds vacío (no hay prospectos).
    public function getNonLeadSendMessageModalInfo(WhatsAppMetaAPIGetNonLeadSendMessageModalInfoRequest $req)
    {
        $modalDTO = resolve(WhatsAppMetaAPIService::class)->getMessageModalInfo(collect([]));
        return $this->getSuccessResponse(new WhatsAppMetaAPIMessageModalResource($modalDTO));
    }


    // @todo Refactorizar, encapsular lógica.
    // Se usa en el modal de envío de mensajes a no-prospectos.
    // Misma lógica que getConversationWindowInfo pero recibe el teléfono directo (en vez de un LeadContactPhone)
    public function getNonLeadSendMessageModalConversationInfo(
        WhatsAppMetaAPIGetNonLeadSendMessageModalConversationInfoRequest $req
    ) {
        // Si las conversaciones están bloqueadas, no mostrar conversación reciente (el envío sigue permitido)
        $permission = $req->client->clientSettings->whatsapp_meta_api_conversations_permission;
        if ($permission === 'none') {
            return $this->getSuccessResponse([
                'recentConversationMessages' => [], 'isConversationWindowOpen' => false,
            ]);
        }

        $connection = $req->user->whatsAppMetaAPIConnection;
        if (!$connection) {
            return $this->getSuccessResponse([
                'recentConversationMessages' => [],
                'isConversationWindowOpen' => false,
            ]);
        }

        $formattedPhone = resolve(PhonesHelper::class)->formatPhoneForWhatsAppWithSettings(
            $req->input('customerPhoneNumber'), $req->client->country_code, $req->client->clientSettings ?? null
        );
        $phoneNumberId = $connection->phone_number_id;

        $messages = resolve(WhatsAppConversationMessageService::class)->listConversation(
            $phoneNumberId, $formattedPhone, ['limit' => 10]
        );

        $recentConversationMessages = $messages
            ->map(fn ($msg) => (new WhatsAppConversationMessageDTO($msg))->toArray())
            ->values()
            ->toArray()
        ;

        $lastIncomingMsg = $messages->last(function ($msg) {
            return $msg->direction === WhatsAppConversationMessage::DIRECTION_INCOMING;
        });
        $isConversationWindowOpen = false;
        if ($lastIncomingMsg && $lastIncomingMsg->metaReceivedMessageTimestamp) {
            $diffMinutes = now()->diffInMinutes($lastIncomingMsg->metaReceivedMessageTimestamp, true);
            $isConversationWindowOpen = $diffMinutes < (24 * 59);
        }

        return $this->getSuccessResponse([
            'isConversationWindowOpen' => $isConversationWindowOpen,
            'recentConversationMessages' => $recentConversationMessages,
        ]);
    }

}
