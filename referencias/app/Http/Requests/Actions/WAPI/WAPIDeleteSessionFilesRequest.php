<?php

namespace App\Http\Requests\Actions\WAPI;

use DateTime;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class WAPIDeleteSessionFilesRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'phoneNumber' => 'required|numeric',
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;
                if (!$isSuperUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }
                
                if (!request()->client->clientSettings->enable_wapi) {
                    $validator->errors()->add('wapi', 'wapi_is_not_enabled');
                    return false;
                }
            }
        });
    }

}
