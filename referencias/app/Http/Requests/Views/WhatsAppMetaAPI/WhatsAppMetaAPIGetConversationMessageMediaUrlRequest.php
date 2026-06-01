<?php

namespace App\Http\Requests\Views\WhatsAppMetaAPI;

use App\Http\Requests\APIBaseRequest;


class WhatsAppMetaAPIGetConversationMessageMediaUrlRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');

                if (!$client->clientSettings->enable_whatsapp_meta_api) {
                    $validator->errors()->add('whatsapp_meta_api', 'whatsapp_meta_api_is_not_enabled');
                    return false;
                }
            });
        }
    }

}
