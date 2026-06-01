<?php

namespace App\Http\Requests\Actions;
use App\Http\Requests\APIBaseRequest;

class DeleteClientyConfigEmailTemplateRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $clientyClientId = (int) config('app.clienty.client_id');
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
