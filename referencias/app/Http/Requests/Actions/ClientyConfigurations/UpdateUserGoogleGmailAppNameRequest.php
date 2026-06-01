<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use App\Http\Requests\APIBaseRequest;


class UpdateUserGoogleGmailAppNameRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'googleGmailAppName' => ['required', 'string', 'in:clienty-gmail-app,clienty-gmail-app-2'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');
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
            });
        }
    }


    public function getGoogleGmailAppName(): string
    {
        $validated = parent::validated();
        return $validated['googleGmailAppName'];
    }

}
