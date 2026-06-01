<?php

namespace App\DTO\WhatsAppMetaAPI;

use Illuminate\Support\Arr;
use App\Models\MongoDB\WhatsAppMetaAPI\WhatsAppConversationMessage;


class WhatsAppConversationMessageDTO
{

    public string $id;
    public string $type;
    public ?string $body;
    public ?array $media;
    public ?array $buttons;
    public bool $hasMedia;
    public int $sentDateTs;
    public ?string $mimeType;
    public bool $isForwarded;
    public ?string $metaStatus;
    public ?string $customerName;
    public bool $isFromClientyUser;
    public array | string | null $metaError;


    public function __construct(WhatsAppConversationMessage $msg)
    {
        $this->isForwarded = false;
        $this->id = (string) $msg->_id;
        $this->body = $this->extractBody($msg);
        $this->buttons = $this->extractButtons($msg);
        $this->customerName = $this->extractCustomerName($msg);
        $this->type = $this->mapMessageType($msg->messageType);
        $this->isFromClientyUser = $msg->direction === WhatsAppConversationMessage::DIRECTION_OUTGOING;
        
        $this->media = $msg->media;
        $this->mimeType = is_array($msg->media) ?
            ($msg->media['mime_type'] ?? $msg->media['mimeType'] ?? null)
            : null
        ;

        // hasMedia debe cubrir tanto adjuntos de MySQL como media persistida en el bucket de conversaciones.
        // Los voicenotes salientes nuevos no tienen clientyWhatsAppAttachmentId, pero sí metaId/clientyFileInfo.
        $this->hasMedia = is_array($msg->media) && (
            !empty($msg->media['id']) ||
            !empty($msg->media['metaId']) ||
            !empty($msg->media['clientyWhatsAppAttachmentId']) ||
            !empty(data_get($msg->media, 'clientyFileInfo.bucketFilePath'))
        );
        $this->sentDateTs = $msg->metaReceivedMessageTimestamp
            ? $msg->metaReceivedMessageTimestamp->timestamp
            : 0
        ;
        $this->metaError = $msg->metaError;
        $this->metaStatus = $msg->metaStatus;
    }


    private function mapMessageType(?string $messageType): string
    {
        return match ($messageType) {
            'voice' => 'ptt',
            'text', 'button', 'list' => 'chat',
            default => $messageType ?? 'chat',
        };
    }


    private function extractCustomerName(WhatsAppConversationMessage $msg): ?string
    {
        $rawPayload = $msg->metaRawPayload;
        if (!is_array($rawPayload)) {
            return null;
        }
        $customerName = null;
        if ($msg->direction === WhatsAppConversationMessage::DIRECTION_INCOMING) {
            $customerName = data_get(
                $rawPayload, 'entry.0.changes.0.value.contacts.0.profile.name'
            );
        }
        return $customerName;
    }


    private function extractBody(WhatsAppConversationMessage $msg): ?string
    {
        $rawPayload = $msg->metaRawPayload;
        if (!is_array($rawPayload)) {
            return null;
        }

        // Mensajes enviados vía API (templates o mensajes libres): body renderizado con variables reemplazadas
        if ($msg->source === WhatsAppConversationMessage::SOURCE_API_MESSAGE) {
            return Arr::get($rawPayload, 'template.renderedBody')
                ?? Arr::get($rawPayload, 'message.text')
            ;
        }

        // Mensajes enviados vía WapBot
        if ($msg->source === WhatsAppConversationMessage::SOURCE_WAP_BOT_MESSAGE) {
            return Arr::get($rawPayload, 'message.text');
        }

        // Mensajes recibidos vía webhook
        $message = Arr::get($rawPayload, 'entry.0.changes.0.value.messages.0');
        $echoMessage = Arr::get($rawPayload, 'entry.0.changes.0.value.message_echoes.0');
        $msgData = $message ?? $echoMessage;

        if (!is_array($msgData)) {
            return null;
        }

        // Texto del mensaje
        $textBody = Arr::get($msgData, 'text.body');
        if ($textBody) {
            return $textBody;
        }

        $buttonResponseText = Arr::get($msgData, 'interactive.button_reply.title');
        if ($buttonResponseText) {
            return $buttonResponseText;
        }

        // Caption de media (image, video, document)
        $type = Arr::get($msgData, 'type');
        if ($type && in_array($type, ['image', 'video', 'document'])) {
            return Arr::get($msgData, "{$type}.caption");
        }

        return null;
    }


    private function extractButtons(WhatsAppConversationMessage $msg): ?array
    {
        if ($msg->source !== WhatsAppConversationMessage::SOURCE_WAP_BOT_MESSAGE) {
            return null;
        }
        $buttons = Arr::get($msg->metaRawPayload, 'message.buttons');
        return is_array($buttons) && !empty($buttons) ? $buttons : null;
    }


    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'body' => $this->body,
            'media' => $this->media,
            'buttons' => $this->buttons,
            'hasMedia' => $this->hasMedia,
            'mimeType' => $this->mimeType,
            'metaError' => $this->metaError,
            'sentDateTs' => $this->sentDateTs,
            'metaStatus' => $this->metaStatus,
            'isForwarded' => $this->isForwarded,
            'customerName' => $this->customerName,
            'isFromClientyUser' => $this->isFromClientyUser,
        ];
    }

}
