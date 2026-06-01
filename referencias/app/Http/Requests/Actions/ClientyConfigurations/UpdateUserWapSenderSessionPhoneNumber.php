<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use App\Http\Requests\APIBaseRequest;


class UpdateUserWapSenderSessionPhoneNumber extends APIBaseRequest
{

    public function rules()
    {
        return [
            'wapSenderSessionPhoneNumber' => ['present', 'nullable', 'string', 'regex:/^\d+$/'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');
                $requestedUser = request()->requestedUser;
                $requestedClient = request()->requestedClient;
                $clientyClientId = (int) config('app.clienty.client_id');
                $isClientyAdminUser = request()->user->is_clienty_admin_user;
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                if (!$isSuperUser && !$isClientyAdminUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }
                if ($requestedUser->client_id != $requestedClient->id) {
                    $validator->errors()->add('client', 'client_and_user_do_not_match');
                    return false;
                }
            });
        }
    }

}