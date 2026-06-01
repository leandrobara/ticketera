<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\API\UserService;
use App\Exceptions\ValidationException;


class AuthSendResetPasswordRequest extends APIBaseRequest
{

    protected $user = null;


    public function rules()
    {
        return [
            'email' => ['required', 'email']
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $email = request()->input('email');
                $client = request()->input('client');

                $user = resolve(UserService::class)->findByClientAndUsernameOrEmail($client, $email);
                if (!$user) {
                    $validator->errors()->add('reset_password', 'invalid_user');
                    return false;
                }
                $this->user = $user;
            }
        });
    }


    public function getUserToResetPassword()
    {
        return $this->user;
    }

}
