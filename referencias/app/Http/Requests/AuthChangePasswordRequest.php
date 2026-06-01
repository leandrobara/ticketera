<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\API\UserService;
use App\Services\API\ClientService;
use Illuminate\Support\Facades\Crypt;
use App\Exceptions\ValidationException;


class AuthChangePasswordRequest extends APIBaseRequest
{
    protected $user = null;
    protected $newPassword = null;

    public function rules()
    {
        return [
            't' => ['required', 'string'],
            'p' => ['required', 'string'],
            'l' => ['required', 'integer'],
            'cid' => ['required', 'string'],
            'uid' => ['required', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $token = request()->input('t');
                $newPasswordLength = (int) request()->input('l');
                $userId = Crypt::decrypt(request()->input('uid'));
                $clientId = Crypt::decrypt(request()->input('cid'));
                $newPassword = Crypt::decrypt(request()->input('p'));

                if (!is_integer($userId) || $userId <= 0 || !$userId) {
                    $validator->errors()->add('change_password', 'invalid_user_id');
                    return false;
                }

                if (!is_integer($clientId) || $clientId <= 0 || !$clientId) {
                    $validator->errors()->add('change_password', 'invalid_client_id');
                    return false;
                }

                if (!is_integer($newPasswordLength) || $newPasswordLength <= 0) {
                    $validator->errors()->add('change_password', 'long_param_is_wrong');
                    return false;
                }

                if (!is_string($newPassword) || !$newPassword) {
                    $validator->errors()->add('change_password', 'invalid_password');
                    return false;
                }

                if (strlen($newPassword) != $newPasswordLength) {
                    $validator->errors()->add('change_password', 'invalid_password');
                    return false;
                }

                if ($clientId != request()->input('client')->id) {
                    $validator->errors()->add('change_password', 'client_does_not_match');
                    return false;
                }

                $client = resolve(ClientService::class)->findOneById($clientId);
                if (!$client) {
                    $validator->errors()->add('change_password', 'client_not_found');
                    return false;
                }

                $user = resolve(UserService::class)->findOneByUserIdAndClientId($userId, $clientId);
                if (!$user) {
                    $validator->errors()->add('change_password', 'user_not_found');
                    return false;
                }

                if ($token != $user->reset_password_token) {
                    $validator->errors()->add('change_password', 'invalid_password_link');
                    return false;
                }

                if (!$user->reset_password_token) {
                    $validator->errors()->add('change_password', 'already_used_reset_password_link');
                    return false;
                }

                $this->user = $user;
                $this->newPassword = $newPassword;
            }
        });
    }


    public function getUserToChangePassword(): User
    {
        return $this->user;
    }


    public function getNewPassword(): string
    {
        return $this->newPassword;
    }
}
