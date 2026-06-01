<?php

namespace App\DTO\WapBot;

use Illuminate\Support\Arr;


class WhatsAppMetaAPIWebhookPayloadDTO
{

    private ?int $timestamp = null;
    private ?string $message = null;
    private ?string $buttonId = null;
    private ?array $mediaData = null;
    private ?string $toNumber = null;
    private ?string $messageId = null;
    private ?string $fromNumber = null;
    private ?string $messageType = null;
    private ?string $buttonTitle = null;
    private ?string $contactName = null;
    private ?array $referralData = null;
    private ?string $invalidReason = null;
    private ?string $phoneNumberId = null;
    private bool $isIncomingMessage = false;
    private bool $isParsableMessage = false;
    private bool $isOutgoingEchoMessage = false;
    private bool $isStatusChangeMessage = false;


    public function __construct(private readonly array $metaRawPayload)
    {
        $this->buildFromPayload();
    }


    private function buildFromPayload(): void
    {
        $value = Arr::get($this->metaRawPayload, 'entry.0.changes.0.value');

        if (!is_array($value)) {
            return;
        }

        $field = Arr::get($this->metaRawPayload, 'entry.0.changes.0.field');
        $statusChangeMsg = Arr::get($this->metaRawPayload, 'entry.0.changes.0.statuses');

        $this->isStatusChangeMessage = $statusChangeMsg ? true : false;
        $this->isIncomingMessage = $field === 'messages' && isset($value['messages']);
        $this->isOutgoingEchoMessage = $field === 'smb_message_echoes' && isset($value['message_echoes']);

        if ($this->isIncomingMessage) {
            $message = Arr::get($value, 'messages.0');
        } elseif ($this->isOutgoingEchoMessage) {
            $message = Arr::get($value, 'message_echoes.0');
        } else {
            return;
        }
        if (!is_array($message)) {
            return;
        }

        $fromNumber = Arr::get($message, 'from');
        if (!$fromNumber) {
            return;
        }

        $this->fromNumber = (string) $fromNumber;
        $this->messageId = Arr::get($message, 'id');
        $this->referralData = Arr::get($message, 'referral');
        $this->timestamp = (int) (Arr::get($message, 'timestamp') ?? time());
        $this->phoneNumberId = Arr::get($value, 'metadata.phone_number_id');

        if ($this->isOutgoingEchoMessage) {
            $toNumber = Arr::get($message, 'to');
            $this->toNumber = $toNumber ? (string) $toNumber : null;
            $this->contactName = null;
        } else {
            $this->contactName = (string) (Arr::get($value, 'contacts.0.profile.name', 'Cliente'));
        }

        $type = Arr::get($message, 'type');
        $interactive = Arr::get($message, 'interactive');

        if ($type === 'interactive' && is_array($interactive)) {
            if (isset($interactive['button_reply'])) {
                $btn = $interactive['button_reply'];
                $this->message = (string) ($btn['title'] ?? '');
                $this->messageType = 'button_reply';
                $this->buttonId = isset($btn['id']) ? (string) $btn['id'] : null;
                $this->buttonTitle = isset($btn['title']) ? (string) $btn['title'] : null;
                $this->isParsableMessage = true;
                return;
            }
            if (isset($interactive['list_reply'])) {
                $row = $interactive['list_reply'];
                $this->message = (string) ($row['title'] ?? '');
                $this->messageType = 'list_reply';
                $this->buttonId = isset($row['id']) ? (string) $row['id'] : null;
                $this->buttonTitle = isset($row['title']) ? (string) $row['title'] : null;
                $this->isParsableMessage = true;
                return;
            }
        }

        if ($type === 'text') {
            $this->message = (string) (Arr::get($message, 'text.body', ''));
            $this->messageType = 'text';
            $this->isParsableMessage = true;
            return;
        }

        $nonTextTypes = ['audio', 'voice', 'image', 'document', 'sticker', 'video', 'location', 'contacts'];
        foreach ($nonTextTypes as $nonTextType) {
            if ($type === $nonTextType || Arr::has($message, $nonTextType)) {
                $this->message = '';
                $this->isParsableMessage = true;
                $this->mediaData = Arr::get($message, $nonTextType);
                $this->messageType = $type ? (string) $type : $nonTextType;
                return;
            }
        }

        $this->message = (string) (Arr::get($message, 'text.body', ''));
        $this->messageType = $type ? (string) $type : 'unknown';
        $this->isParsableMessage = true;
    }


    public function isIncomingMessage(): bool
    {
        return $this->isIncomingMessage;
    }

    public function isStatusChangeMessage(): bool
    {
        return $this->isStatusChangeMessage;
    }

    public function isOutgoingEchoMessage(): bool
    {
        return $this->isOutgoingEchoMessage;
    }

    public function isParsableMessage(): bool
    {
        return $this->isParsableMessage;
    }

    public function getInvalidReason(): ?string
    {
        return $this->invalidReason;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getFromNumber(): ?string
    {
        return $this->fromNumber;
    }

    public function getToNumber(): ?string
    {
        return $this->toNumber;
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    public function getPhoneNumberId(): ?string
    {
        return $this->phoneNumberId;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getMessageType(): ?string
    {
        return $this->messageType;
    }

    public function getButtonId(): ?string
    {
        return $this->buttonId;
    }

    public function getButtonTitle(): ?string
    {
        return $this->buttonTitle;
    }

    public function getReferralData(): ?array
    {
        return $this->referralData;
    }

    public function getMediaData(): ?array
    {
        return $this->mediaData;
    }

    public function hasMediaData(): bool
    {
        return is_array($this->mediaData) && !empty($this->mediaData);
    }

    public function getMetaRawPayload(): array
    {
        return $this->metaRawPayload;
    }

    public function isTextMessage(): bool
    {
        return $this->messageType === 'text';
    }

    public function isInteractiveReply(): bool
    {
        return in_array($this->messageType, ['button_reply', 'list_reply'], true);
    }

    public function isAttachment(): bool
    {
        return in_array(
            $this->messageType,
            ['audio', 'voice', 'image', 'document', 'sticker', 'video', 'location', 'contacts'],
            true
        );
    }

    public function getAttachmentTypeLegend(): string
    {
        return match ($this->messageType) {
            'video' => 'un video',
            'image' => 'una imagen',
            'sticker' => 'un sticker',
            'contacts' => 'un contacto',
            'document' => 'un documento',
            'location' => 'una ubicación',
            'audio', 'voice' => 'un audio',
            default => 'un archivo adjunto',
        };
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'button_id' => $this->buttonId,
            'to_number' => $this->toNumber,
            'timestamp' => $this->timestamp,
            'message_id' => $this->messageId,
            'from_number' => $this->fromNumber,
            'button_title' => $this->buttonTitle,
            'contact_name' => $this->contactName,
            'message_type' => $this->messageType,
            'referral_data' => $this->referralData,
            'invalid_reason' => $this->invalidReason,
            'phone_number_id' => $this->phoneNumberId,
            'is_parsable_message' => $this->isParsableMessage,
            'is_incoming_message' => $this->isIncomingMessage,
            'is_outgoing_echo_message' => $this->isOutgoingEchoMessage,
        ];
    }

}
