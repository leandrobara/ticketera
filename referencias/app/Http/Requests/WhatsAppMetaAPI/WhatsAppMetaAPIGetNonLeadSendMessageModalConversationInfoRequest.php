<?php

namespace App\Http\Requests\WhatsAppMetaAPI;

use App\Http\Requests\APIBaseRequest;


class WhatsAppMetaAPIGetNonLeadSendMessageModalConversationInfoRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'customerPhoneNumber' => ['required', 'string'],
            'whatsAppMetaAPIConnectionId' => ['required', 'integer'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                if (!request()->client->clientSettings->enable_whatsapp_meta_api) {
                    $validator->errors()->add('whatsapp_meta_api', 'whatsapp_meta_api_is_not_enabled');
                    return false;
                }

                if (!request()->user->whatsAppMetaAPIConnection) {
                    $validator->errors()->add('whatsapp_meta_api', 'whatsapp_meta_api_connection_does_not_exist');
                    return false;
                }

                $whatsAppMetaAPIConnectionId = (int) request()->input('whatsAppMetaAPIConnectionId');
                if ($whatsAppMetaAPIConnectionId !== (int) request()->user->whatsAppMetaAPIConnection->id) {
                    $validator->errors()->add('whatsAppMetaAPIConnectionId', 'connection_does_not_match_user');
                    return false;
                }
            });
        }
    }
}
