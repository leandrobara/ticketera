<?php

namespace App\Http\Requests\Views\ClientyConfigurations;

use App\Http\Requests\APIBaseRequest;


class ClientEmailQuotaInfoRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $loginUser = request()->user;
                $client = request()->input('client');
                $clientyClientId = (int) config('app.clienty.client_id');
                $isClientyAdminUser = $loginUser->is_clienty_admin_user;
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                if (!$isSuperUser && !$isClientyAdminUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }
            });
        }
    }

}
