<?php

namespace App\Http\Requests;

use App\Rules\InUserReturnFields;
use App\Repositories\UserRepository;


class EnableUserRequest extends APIBaseRequest
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
                $userToEnable = request()->userToEnable;
                $client = request()->input('client');

                if ($userToEnable->client_id != $client->id || $loginUser->client_id != $client->id) {
                    $validator->errors()->add('client_id', 'user_client_does_not_match_with_authenticated_client');
                    return false;
                }
                if ($loginUser->id === $userToEnable->id) {
                    $validator->errors()->add('user', 'user_to_enable_can_not_be_same_login_user');
                    return false;
                }
                if ($userToEnable->enabled) {
                    $validator->errors()->add('user', 'user_is_already_enabled');
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
