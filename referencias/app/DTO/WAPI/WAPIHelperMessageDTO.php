<?php

namespace App\DTO\WAPI;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\Models\WhatsAppAttachment;
use App\Models\WhatsAppSendingMessage;
use App\Helpers\WhatsAppVariablesHelper;
use App\Helpers\WhatsAppAttachmentHelper;


class WAPIHelperMessageDTO
{

    private function __construct(
        public string $phoneNumber,
        public string $chatMessage,
        public string $wapiSessionPhoneNumber,
        public string | null $attachmentUrl = null,
        public string | null $attachmentName = null,
    ) {
    }


    public static function build(
        string $phoneNumber,
        string $chatMessage,
        string $wapiSessionPhoneNumber
    ): WAPIHelperMessageDTO {
        $dto = new WAPIHelperMessageDTO($phoneNumber, $chatMessage, $wapiSessionPhoneNumber);
        return $dto;
    }


    public static function buildFromWhatsAppSendingMessage(WhatsAppSendingMessage $wapSendingMsg): WAPIHelperMessageDTO
    {
        $chatMessage = $wapSendingMsg->whatsAppSending->WhatsAppSendingMessageText->message;
        $chatMessage = WhatsAppVariablesHelper::replaceVariables(
            $chatMessage, $wapSendingMsg->leadContactPhone, $wapSendingMsg->user
        );
        $phoneNumber = $wapSendingMsg->phone_number;
        $wapiSessionPhoneNumber = $wapSendingMsg->user->wapi_session_phone_number;
        
        $dto = new WAPIHelperMessageDTO($phoneNumber, $chatMessage, $wapiSessionPhoneNumber);
        if ($wapSendingMsg->whatsAppSending->whatsapp_attachment_id) {
            $wapAttachment = $wapSendingMsg->whatsAppSending->whatsAppAttachment;
            $dto->attachmentName = $wapAttachment->original_filename;
            $dto->attachmentUrl = resolve(WhatsAppAttachmentHelper::class)->getTemporaryUrl($wapAttachment, 5);
        }

        return $dto;
    }


    public function toArray(): array
    {
        $data = [
            'phoneNumber' => $this->phoneNumber,
            'chatMessage' => $this->chatMessage,
        ];
        if ($this->attachmentUrl) {
            $data['attachmentUrl'] = $this->attachmentUrl;
            $data['attachmentName'] = $this->attachmentName;
        }
        return $data;
    }

}
