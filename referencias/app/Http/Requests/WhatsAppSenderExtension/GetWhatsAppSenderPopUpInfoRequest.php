<?php

namespace App\Http\Requests\WhatsAppSenderExtension;

use App\Http\Requests\APIBaseRequest;
use App\Services\API\WhatsAppSendingService;


class GetWhatsAppSenderPopUpInfoRequest extends APIBaseRequest
{

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
                
                if (!$clientSettings->enable_whatsapp_sender_extension) {
                    $validator->errors()->add('whatsapp_sending', 'whatsapp_sender_extension_is_not_enabled');
                    return false;
                }
            }
        });
    }

}
