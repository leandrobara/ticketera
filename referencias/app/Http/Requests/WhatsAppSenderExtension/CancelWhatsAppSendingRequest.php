<?php

namespace App\Http\Requests\WhatsAppSenderExtension;

use App\Models\WhatsAppSending;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\WhatsAppSendingService;


class CancelWhatsAppSendingRequest extends APIBaseRequest
{

    public $currentSending;


    public function rules()
    {
        return [];
    }

    
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $user = request()->input('user');
                $client = request()->input('client');
                $clientSettings = $client->clientSettings;
                
                $currentSending = resolve(WhatsAppSendingService::class)->findCurrentSendingByUserAndType(
                    $user, WhatsAppSending::WAP_SENDER_TYPE
                );
                if (!$currentSending) {
                    $validator->errors()->add('whatsapp_sending', 'current_user_whatsapp_sending_does_not_exist');
                    return false;
                }
                if ($currentSending->client_id != $client->id) {
                    $validator->errors()->add('whatsapp_sending', 'whatsapp_sending_does_not_belong_to_client');
                    return false;
                }
                if ($currentSending->finished_date) {
                    $validator->errors()->add('whatsapp_sending', 'whatsapp_sending_was_already_finished');
                    return false;
                }
                if ($currentSending->cancelled_date) {
                    $validator->errors()->add('whatsapp_sending', 'whatsapp_sending_was_already_cancelled');
                    return false;
                }
                
                $this->currentSending = $currentSending;
            }
        });
    }


    public function getCurrentSending(): WhatsAppSending
    {
        return $this->currentSending;
    }

}
