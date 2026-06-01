<?php

namespace App\Http\Requests;

use App\Rules\InUserReturnFields;
use App\Repositories\UserRepository;


class UpdateUserRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'email' => ['sometimes', 'email'],
            'name' => ['sometimes', 'string'],
            'username' => ['sometimes', 'string'],
            'enabled' => ['sometimes', 'boolean'],
            'wapi_is_synced' => ['sometimes', 'boolean'],
            'phone' => ['sometimes', 'nullable', 'string'],
            'email_is_verified' => ['sometimes', 'boolean'],
            'email_sign_enabled' => ['sometimes', 'boolean'],
            'last_name' => ['sometimes', 'nullable', 'string'],
            'email_sign' => ['sometimes', 'string', 'nullable'],
            'enable_emails_reception' => ['sometimes', 'boolean'],
            'enabled_to_receive_leads' => ['sometimes', 'boolean'],
            'email_from_name' => ['sometimes', 'string', 'nullable'],
            'email_from_address' => ['sometimes', 'email', 'nullable'],
            'enable_new_lead_browser_alert' => ['sometimes', 'boolean'],
            'type' => ['sometimes', 'string', 'in:admin,readonly,sales'],
            'wapi_session_phone_number' => ['sometimes', 'nullable', 'string'],
            'enabled_export_leads_emails_reception' => ['sometimes', 'boolean'],
            'enabled_delete_leads_emails_reception' => ['sometimes', 'boolean'],
            'enable_alert_expiration_browser_alert' => ['sometimes', 'boolean'],
            'enable_alert_proposal_interaction_alert' => ['sometimes', 'boolean'],

            'password' => ['sometimes', 'string', 'nullable'],
            'repeated_password' => ['sometimes', 'string', 'nullable'],

            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InUserReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $user = request()->userToUpdate;
                $client = request()->input('client');
                $clientId = $client->id;

                if ($user->client_id != $clientId) {
                    $validator->errors()->add('client_id', 'user_client_does_not_match_with_authenticated_client');
                    return false;
                }

                $password = request()->input('password');
                $repeatedPassword = request()->input('repeated_password');
                if ($password || $repeatedPassword) {
                    $nonMatchingPassword = $password != $repeatedPassword;
                    if ($nonMatchingPassword || strlen($password) < 6 || strlen($repeatedPassword) < 6) {
                        $validator->errors()->add('password', 'user_client_does_not_match_with_authenticated_client');
                        return false;
                    }
                }

                $email = request()->input('email');
                $username = request()->input('username');
                $userList = resolve(UserRepository::class)->findAllByClient($client);

                if ($username || $email) {
                    $otherUsers = $userList->filter(function ($u) use ($user) {
                        return $u->id != $user->id;
                    });
                    
                    if ($username) {
                        $usernameExists = $otherUsers->filter(function ($u) use ($username) {
                            return $u->username === $username;
                        })->isNotEmpty();
                        if ($usernameExists) {
                            $validator->errors()->add('username', 'user_username_already_exists');
                            return false;
                        }
                    }

                    if ($email) {
                        $email = strtolower(trim($email));
                        $emailExists = $otherUsers->filter(function ($u) use ($email) {
                            return $u->email === $email;
                        })->isNotEmpty();
                        if ($emailExists) {
                            $validator->errors()->add('email', 'user_email_already_exists');
                            return false;
                        }
                    }
                }

                $enabledToReceiveLeads = request()->input('enabled_to_receive_leads');
                if ($enabledToReceiveLeads === false) {
                    $otherEnabledUsers = $userList->filter(function ($u) use ($user) {
                        return $u->id != $user->id && $u->enabled && $u->enabled_to_receive_leads;
                    });
                    if ($otherEnabledUsers->count() < 1) {
                        $validator->errors()->add('enabled_to_receive_leads', 'no_other_enabled_users');
                        return false;
                    }
                }

                $type = request()->input('type');
                if ($type && $type != 'admin') {
                    $adminUsersCount = $userList
                        ->where('enabled', 1)
                        ->where('type', 'admin')
                        ->where('id', '!=', $user->id)
                        ->count()
                    ;
                    if ($adminUsersCount < 1) {
                        $validator->errors()->add('user_type', 'no_other_admin_users');
                        return false;
                    }
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
