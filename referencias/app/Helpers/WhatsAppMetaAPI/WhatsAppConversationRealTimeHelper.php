<?php

namespace App\Helpers\WhatsAppMetaAPI;

use Pusher\Pusher;
use Illuminate\Support\Facades\Redis;
use App\DTO\WhatsAppMetaAPI\WhatsAppConversationMessageDTO;
use App\Models\MongoDB\WhatsAppMetaAPI\WhatsAppConversationMessage;


class WhatsAppConversationRealTimeHelper
{

    private const ACTIVE_VIEWER_TTL_SECONDS = 300; // 5 minutos
    private const REDIS_CONNECTION = 'whatsapp_conversations_real_time';


    public function __construct(
        private readonly Pusher $pusher,
    ) {
    }


    /**
     * Registra que un client tiene al menos un usuario viendo conversaciones.
     * Se llama desde el endpoint y se renueva cada 2 minutos desde el frontend.
     */
    public function registerWhatsAppConversationsActiveViewer(int $clientId): void
    {
        // SET key value EX seconds — la key se auto-borra si no se renueva
        Redis::connection(self::REDIS_CONNECTION)->set(
            "whatsapp_conversations_opened:{$clientId}", 1, 'EX', self::ACTIVE_VIEWER_TTL_SECONDS
        );
    }


    /**
     * Verifica si hay algún usuario del client mirando conversaciones.
     */
    public function clientIsViewingMessages(int $clientId): bool
    {
        return (bool) Redis::connection(self::REDIS_CONNECTION)->exists(
            "whatsapp_conversations_opened:{$clientId}"
        );
    }


    /**
     * Si hay viewers activos, envía el mensaje nuevo vía Pusher.
     * Se llama desde los 3 jobs de guardado de mensajes. El clientId se pasa desde
     * afuera para evitar queries innecesarias (2 de 3 jobs ya lo tienen disponible).
     */
    public function broadcastNewConversationMessage(WhatsAppConversationMessage $message, int $clientId): void
    {
        if (!$this->clientIsViewingMessages($clientId)) {
            return;
        }

        $dto = new WhatsAppConversationMessageDTO($message);
        $payload = [
            'message' => $dto->toArray(),
            'customerPhoneNumber' => $message->customerPhoneNumber,
            'customerName' => $this->extractCustomerName($message),
            'metaConnectedPhoneNumberId' => $message->metaConnectedPhoneNumberId,
        ];

        $channel = "private-whatsapp-conversations.{$clientId}";
        $this->pusher->trigger($channel, 'new-conversation-message', $payload);
    }


    /**
     * Si hay viewers activos, envía la actualización de estado de un mensaje vía Pusher.
     * Se usa desde el job que procesa webhooks de status de Meta (sent/delivered/read/failed).
     */
    public function broadcastConversationMessageStatusUpdate(
        WhatsAppConversationMessage $conversationMsg,
        int $clientId
    ): void {
        if (!$this->clientIsViewingMessages($clientId)) {
            return;
        }

        $payload = [
            'metaError' => $conversationMsg->metaError,
            'metaStatus' => $conversationMsg->metaStatus,
            'conversationMessageId' => (string) $conversationMsg->_id,
            'customerPhoneNumber' => $conversationMsg->customerPhoneNumber,
            'metaConnectedPhoneNumberId' => $conversationMsg->metaConnectedPhoneNumberId,
        ];

        $channel = "private-whatsapp-conversations.{$clientId}";
        $this->pusher->trigger($channel, 'conversation-message-status-updated', $payload);
    }


    /**
     * Extrae el nombre del cliente desde el payload de webhook (solo mensajes incoming).
     */
    private function extractCustomerName(WhatsAppConversationMessage $message): ?string
    {
        if ($message->direction !== WhatsAppConversationMessage::DIRECTION_INCOMING) {
            return null;
        }
        return data_get($message->metaRawPayload, 'entry.0.changes.0.value.contacts.0.profile.name');
    }

}
