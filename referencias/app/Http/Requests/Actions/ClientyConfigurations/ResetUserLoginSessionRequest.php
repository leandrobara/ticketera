<?php

namespace App\Http\Requests\Actions\ClientyConfigurations;

use App\Services\API\UserService;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class ResetUserLoginSessionRequest extends APIBaseRequest
{

    private Collection $users;


    public function rules()
    {
        return [
            'user_id' => ['required', 'array'],
            'user_id.*' => ['required', 'integer'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');
                $userIds = request()->input('user_id');
                $requestedClient = request()->requestedClient;
                $clientyClientId = (int) config('app.clienty.client_id');
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                if (!$isSuperUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }

                $users = resolve(UserService::class)->findByClientAndIds($requestedClient, $userIds);
                if ($users->count() != count($userIds)) {
                    $validator->errors()->add('user_id', 'user_id_does_not_match_with_requested_client');
                    return false;
                }
                $this->users = $users;
            });
        }
    }


    public function getUsers(): Collection
    {
        return $this->users;
    }

}
