<?php

namespace App\Repositories;

use Exception;
use Illuminate\Support\Collection;
use App\Models\WhatsAppSendingMessageText;
use App\Repositories\Traits\VoidClearCache;


class WhatsAppSendingMessageTextRepository
{
    
    use VoidClearCache;


    public function findOrCreate(string $message): WhatsAppSendingMessageText
    {
        $hash = WhatsAppSendingMessageText::buildHash($message);
        return WhatsAppSendingMessageText::firstOrCreate(['hash' => $hash], ['message' => $message]);
    }

}
