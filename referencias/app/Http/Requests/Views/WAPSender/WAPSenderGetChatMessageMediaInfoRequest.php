<?php

namespace App\Http\Requests\Views\WAPSender;

use App\Models\Lead;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class WAPSenderGetChatMessageMediaInfoRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');
                $loginUser = request()->input('user');
                $leadContactPhone = request()->leadContactPhone;
                $leadBelongsToLoginUser = $leadContactPhone->lead->user->id == $loginUser->id;

                if ($loginUser->type != 'admin' && !$leadBelongsToLoginUser) {
                    $validator->errors()->add('user', 'current_user_is_not_admin');
                    return false;
                }
                if (!$leadContactPhone->lead->user->wap_sender_session_phone_number) {
                    $validator->errors()->add('wap_sender', 'wap_sender_is_not_enabled_for_user');
                    return false;
                }
                if (!$client->clientSettings->enable_whatsapp_sender_job_sending) {
                    $validator->errors()->add('wapi', 'wap_sender_is_not_enabled_for_client');
                    return false;
                }
                if (!$client->clientSettings->enable_wapi_conversation_chat) {
                    $validator->errors()->add('wapi', 'wapi_conversation_chat_is_not_enabled');
                    return false;
                }
                
                if ($leadContactPhone->client_id != $client->id) {
                    $validator->errors()->add('client', 'lead_contact_phone_does_not_belong_to_client');
                    return false;
                }
            });
        }
    }

}
