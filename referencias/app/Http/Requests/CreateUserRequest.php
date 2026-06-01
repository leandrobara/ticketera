<?php

namespace App\Http\Requests;

use App\Rules\InUserReturnFields;
use App\Repositories\UserRepository;


class CreateUserRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'email' => ['required', 'email'],
            'name' => ['required', 'string'],
            'username' => ['required', 'string'],
            'phone' => ['sometimes', 'nullable', 'string'],
            'last_name' => ['sometimes', 'nullable', 'string'],
            'enabled_to_receive_leads' => ['sometimes', 'boolean'],
            'type' => ['required', 'string', 'in:admin,readonly,sales'],
            
            'password' => ['required', 'string'],
            'repeated_password' => ['required', 'string'],

            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InUserReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $clientId = $client->id;

                $password = request()->input('password');
                $repeatedPassword = request()->input('repeated_password');
                $nonMatchingPassword = $password != $repeatedPassword;
                if ($nonMatchingPassword || strlen($password) < 6 || strlen($repeatedPassword) < 6) {
                    $validator->errors()->add('password', 'user_client_does_not_match_with_authenticated_client');
                    return false;
                }
                
                $email = request()->input('email');
                $username = request()->input('username');
                $userList = resolve(UserRepository::class)->findAllByClient($client);

                $usernameExists = $userList->filter(function ($u) use ($username) {
                    return $u->username === $username;
                })->isNotEmpty();
                if ($usernameExists) {
                    $validator->errors()->add('username', 'user_username_already_exists');
                    return false;
                }

                $email = strtolower(trim($email));
                $emailExists = $userList->filter(function ($u) use ($email) {
                    return $u->email === $email;
                })->isNotEmpty();
                if ($emailExists) {
                    $validator->errors()->add('email', 'user_email_already_exists');
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
        if ($validated['password'] ?? null) {
            unset($validated['repeated_password']);
        }
        return $validated;
    }

}
