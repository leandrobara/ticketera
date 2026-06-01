<?php

namespace App\Http\Requests\WhatsAppMetaAPI;

use App\Models\User;
use App\Models\Client;
use App\Http\Requests\APIBaseRequest;


class HandleOAuthCallbackRequest extends APIBaseRequest
{

    private User $user;
    private string $code;
    private Client $client;

    public function rules()
    {
        return [
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $code = request()->input('code');
                $state = request()->input('state');
                
                $stateJson = base64_decode($state);
                $stateArr = json_decode($stateJson, true);
                if (!is_array($stateArr)) {
                    $validator->errors()->add('state', 'state must be an array');
                }
                if (!$stateArr['client_id']) {
                    $validator->errors()->add('state', 'missing client_id within state array');
                }
                if (!$stateArr['client_subdomain']) {
                    $validator->errors()->add('state', 'missing client_subdomain within state array');
                }
                if (!$stateArr['user_id']) {
                    $validator->errors()->add('state', 'missing user_id within state array');
                }
                if (!$stateArr['user_username']) {
                    $validator->errors()->add('state', 'missing user_username within state array');
                }
                
                $clientId = (int) $stateArr['client_id'];
                $subdomain = trim(strtolower($stateArr['client_subdomain']));
                $client = Client::where('subdomain', $subdomain)->where('id', $clientId)->first();
                if (!$client) {
                    $validator->errors()->add('client', 'non existent client');
                }

                $userId = (int) $stateArr['user_id'];
                $username = trim(strtolower($stateArr['user_username']));
                $user = User::where('id', $userId)
                    ->where('client_id', $clientId)
                    ->where('username', $username)
                    ->first()
                ;
                if (!$user) {
                    $validator->errors()->add('user', 'non existent user');
                }

                

                $this->code = $code;
                $this->user = $user;
                $this->client = $client;
            });
        }
    }


    public function getStateUser(): User
    {
        return $this->user;
    }

    public function getStateClient(): Client
    {
        return $this->client;
    }

    public function getCode(): string
    {
        return $this->code;
    }

}
