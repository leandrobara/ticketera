<?php

namespace App\Http\Requests\Actions;

use App\Models\User;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsValidWhatsAppPhoneNumber;


class SyncWapiRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'wapi_session_phone_number' => ['required', 'digits_between:10,15', new IsValidWhatsAppPhoneNumber()],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $isWapiEnabled = request()->client->clientSettings->enable_wapi;
                if (!$isWapiEnabled) {
                    $validator->errors()->add('wapi', 'wapi_is_not_enabled');
                    return false;
                }
            });
        }
    }

}
