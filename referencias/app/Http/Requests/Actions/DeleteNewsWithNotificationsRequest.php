<?php

namespace App\Http\Requests\Actions;
use App\Http\Requests\APIBaseRequest;


class DeleteNewsWithNotificationsRequest extends APIBaseRequest
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
                $isClientyAdminUser = $loginUser->is_clienty_admin_user;
                $clientyClientId = (int) config('app.clienty.client_id');

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
