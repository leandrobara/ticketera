<?php

namespace App\Services\API;

use App\Models\WhatsAppSendingMessageText;
use App\Repositories\WhatsAppSendingMessageTextRepository;


class WhatsAppSendingMessageTextService
{

    
    public function __construct(
        protected readonly WhatsAppSendingMessageTextRepository $whatsAppSendingMessageTextRepository
    ) {
    }


    public function findOrCreate(string $message): WhatsAppSendingMessageText
    {
        return $this->whatsAppSendingMessageTextRepository->findOrCreate($message);
    }

}
