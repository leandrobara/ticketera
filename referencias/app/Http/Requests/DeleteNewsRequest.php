<?php

namespace App\Http\Requests;

use App\Rules\InTaskReturnFields;


class DeleteNewsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }

    
    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $clientyClientId = (int) config('app.clienty.client_id');
                $user = request()->input('user');
                $client = request()->input('client');
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                
                if (!$isSuperUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }
            });
        }
    }

}
