<?php

namespace App\Http\Requests\Views;

use App\Models\Lead;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class WAPIListMessagesRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'limit' => ['sometimes', 'int'],
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
                if ($leadContactPhone->lead->user->wapi_is_paused) {
                    $validator->errors()->add('wapi', 'wapi_is_paused');
                    return false;
                }
                if (!request()->client->clientSettings->enable_wapi) {
                    $validator->errors()->add('wapi', 'wapi_is_not_enabled');
                    return false;
                }
                if (!$client->clientSettings->enable_wapi_conversation_chat) {
                    $validator->errors()->add('wapi', 'wapi_conversation_chat_is_not_enabled');
                    return false;
                }
                if (!$leadContactPhone->lead->user->wapi_session_phone_number) {
                    $validator->errors()->add('wapi', 'lead_user_is_not_synced_with_wapi');
                    return false;
                }
                if (!$leadContactPhone->lead->user->wapi_is_synced) {
                    $validator->errors()->add('wapi', 'lead_user_is_not_synced_with_wapi');
                    return false;
                }
                
                if ($leadContactPhone->client_id != $client->id) {
                    $validator->errors()->add('client', 'lead_contact_phone_does_not_belong_to_client');
                    return false;
                }
            });
        }
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        $val['limit'] = $val['limit'] ?? 500;
        return $val;
    }

}
