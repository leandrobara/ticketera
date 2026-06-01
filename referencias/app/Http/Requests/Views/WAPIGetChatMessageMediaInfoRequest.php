<?php

namespace App\Http\Requests\Views;

use App\Models\Lead;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class WAPIGetChatMessageMediaInfoRequest extends APIBaseRequest
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
                if (!request()->client->clientSettings->enable_wapi) {
                    $validator->errors()->add('wapi', 'wapi_is_not_enabled');
                    return false;
                }
                if (!request()->user->wapi_session_phone_number) {
                    $validator->errors()->add('wapi', 'user_is_not_synced_with_wapi');
                    return false;
                }
                if (!request()->user->wapi_is_synced) {
                    $validator->errors()->add('wapi', 'user_is_not_synced_with_wapi');
                    return false;
                }
            });
        }
    }

}
