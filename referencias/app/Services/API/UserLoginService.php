<?php

namespace App\Services\API;

use App\Services\Traits\GetRealIP;
use App\Http\Requests\AuthLoginRequest;
use App\Repositories\UserLoginRepository;


class UserLoginService
{

    use GetRealIP;

    private $userLoginRepository;


    public function __construct(UserLoginRepository $userLoginRepository)
    {
        $this->userLoginRepository = $userLoginRepository;
    }


    public function registerLogin(array $authData, AuthLoginRequest $request)
    {
        $data = [
            'ip' => $this->getIp(),
            'user_id' => $authData['user']->id,
            'user_agent' => $request->userAgent(),
            'client_id' => $authData['user']->client_id,
            'is_super_admin' => $authData['isSuperUser'] ?? false,
            'super_user_id' => $authData['superUser']?->id ?? null,
        ];
        $this->userLoginRepository->create($data);
    }

}
