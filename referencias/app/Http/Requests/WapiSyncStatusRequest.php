<?php
namespace App\Http\Requests;

use App\Models\User;
use App\Models\ClientSettings;
use App\Http\Requests\APIBaseRequest;


class WapiSyncStatusRequest extends APIBaseRequest
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
                if (request()->user->wapi_is_paused) {
                    $validator->errors()->add('wapi', 'wapi_is_paused');
                    return false;
                }
            });
        }
    }
}
