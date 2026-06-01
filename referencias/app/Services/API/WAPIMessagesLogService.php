<?php

namespace App\Services\API;

use DateTime;
use App\Models\User;
use Illuminate\Support\Collection;
use App\Models\WhatsAppSendingMessage;
use App\Models\MongoDB\WAPIMessageLog;


//
// @deprecated - 13 Marzo 2024
// NO se usa más. Nunca se usaron estos logs y ocupan mucho
//
class WAPIMessagesLogService
{


    // public function __construct()
    // {
    // }


    //
    // Se llamaba a este método desde los Jobs de envío (luego del envío)
    //
    // public function saveSentMessageLog(WhatsAppSendingMessage $wapSendingMessage, array $WAPISentMsgResponse): bool
    // {
    //     $date = new DateTime('now');
    //     $wapiMsgLogData = [
    //         'type' => $wapSendingMessage->type,
    //         'wapi_response' => $WAPISentMsgResponse,
    //         'client_id' => $wapSendingMessage->client_id,
    //         'user' => $this->getUserData($wapSendingMessage->user),
    //         'whatsapp_sending_message_id' => $wapSendingMessage->id,
    //         'lead_contact_phone_id' => $wapSendingMessage->lead_contact_phone_id,
    //         'whatsapp_sending_id' => $wapSendingMessage->whatsapp_sending_id,
    //     ];
    //     $wapiMsgLog = new WAPIMessageLog();
    //     $wapiMsgLog->createdAt = $date;
    //     $wapiMsgLog->log = $wapiMsgLogData;
    //     $wapiMsgLog->event = 'sent_message';
    //     $wapiMsgLog->createdAtTs = $date->getTimestamp();
    //     $wapiMsgLog->save();
    //     return true;
    // }


    // private function getUserData(array | User $user): array
    // {
    //     if (is_array($user)) {
    //         return [
    //             'id' => $user['id'],
    //             'name' => $user['name'],
    //             'email' => $user['email'],
    //             'username' => $user['username'],
    //             'last_name' => $user['last_name'],
    //         ];
    //     }
    //     return [
    //         'id' => $user->id,
    //         'name' => $user->name,
    //         'email' => $user->email,
    //         'username' => $user->username,
    //         'last_name' => $user->last_name,
    //     ];
    // }

}
