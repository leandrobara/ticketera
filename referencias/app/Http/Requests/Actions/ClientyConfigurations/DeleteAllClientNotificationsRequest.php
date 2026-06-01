<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class DeleteAllClientNotificationsRequest extends APIBaseRequest
{
    protected $notifications;

    public function rules()
    {
        return [];
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

}
