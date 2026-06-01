<?php

namespace App\Http\Requests\GoogleAPI;

use App\Helpers\GoogleAPIHelper;
use App\Rules\InTaskReturnFields;
use App\Models\GoogleAPIUserToken;
use App\Http\Requests\APIBaseRequest;


class CreateMyGoogleGmailAPITokenFromAuthCodeRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'cid' => ['required', 'int'],
            'uid' => ['required', 'int'],
            'code' => ['required', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $user = request()->user;
                $client = request()->client;
                $userId = request()->input('uid');
                $clientId = request()->input('cid');
                if ($client->id != $clientId) {
                    $validator->errors()->add('client_id', 'Invalid client id');
                    return false;
                }
                if ($user->id != $userId) {
                    $validator->errors()->add('user_id', 'Invalid user id');
                    return false;
                }
                if ($user->client_id != $clientId) {
                    $validator->errors()->add('user_id', 'User does not belong to client');
                    return false;
                }
            });
        }
    }


    public function getAuthCode(): string
    {
        return parent::validated()['code'];
    }

}
