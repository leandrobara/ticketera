<?php

namespace App\Http\Requests;

use App\Rules\InUserReturnFields;
use App\Repositories\UserRepository;


class DisableUserRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InUserReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $loginUser = request()->user;
                $userToDisable = request()->userToDisable;
                $client = request()->input('client');

                if ($userToDisable->client_id != $client->id || $loginUser->client_id != $client->id) {
                    $validator->errors()->add('client_id', 'user_client_does_not_match_with_authenticated_client');
                    return false;
                }
                if ($loginUser->id === $userToDisable->id) {
                    $validator->errors()->add('user', 'user_to_enable_can_not_be_same_login_user');
                    return false;
                }
                if (!$userToDisable->enabled) {
                    $validator->errors()->add('user', 'user_is_already_disabled');
                    return false;
                }

                $userList = resolve(UserRepository::class)->findAllByClient($client);
                $otherEnabledUsers = $userList->filter(function ($u) use ($userToDisable) {
                    return $u->id != $userToDisable->id && $u->enabled && $u->enabled_to_receive_leads;
                });
                if ($otherEnabledUsers->count() < 1) {
                    $validator->errors()->add('enabled_to_receive_leads', 'no_other_enabled_users');
                    return false;
                }
            }
        });
    }


    public function validatedAttributes()
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }

}
