<?php
namespace App\Http\Requests;

use App\Models\User;
use App\Models\ClientSettings;
use App\Http\Requests\APIBaseRequest;


class UserWAPSenderSyncStatusRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->client;
                $userToCheck = request()->userToCheck;
                
                if ($userToCheck->client_id != $client->id) {
                    $validator->errors()->add('client', 'client_does_not_match');
                    return false;
                }
            });
        }
    }

}
