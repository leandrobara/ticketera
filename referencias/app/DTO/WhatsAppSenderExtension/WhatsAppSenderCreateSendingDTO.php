<?php

namespace App\DTO\WhatsAppSenderExtension;

use Exception;
use App\Models\User;
use App\DTO\WhatsAppSenderExtension\WhatsAppSenderPhonesMapDTO;


class WhatsAppSenderCreateSendingDTO
{

    public $user;
    public $chatMessage;
    public $phonesMapDTO;


    public function __construct(User $user, array $phonesMapArray, string $chatMessage)
    {
        if (!$phonesMapArray) {
            throw new Exception('whatsapp_sender_create_sending_dto_empty_phones_map_array');
        }
        if (!$chatMessage) {
            throw new Exception('whatsapp_sender_create_sending_dto_empty_chat_message');
        }
        $this->user = $user;
        $this->chatMessage = $chatMessage;
        $this->phonesMapDTO = new WhatsAppSenderPhonesMapDTO($phonesMapArray);
    }

}
