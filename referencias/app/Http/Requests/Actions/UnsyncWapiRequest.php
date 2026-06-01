<?php

namespace App\Http\Requests\Actions;

use App\Models\User;
use App\Models\ClientSettings;
use App\Http\Requests\APIBaseRequest;


class UnsyncWapiRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
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
