<?php

namespace App\Services\Traits;

use App\Models\User;
use App\Exceptions\Services\Traits\GetUserFromRequestTraitException;


trait GetUserFromRequest
{

    private $requestUser = null;


    protected function getUser(): User
    {
        if ($this->requestUser) {
            return $this->requestUser;
        }

        $requestUser = request()->input('user');
        if ($requestUser) {
            $this->setRequestUser($requestUser);
            return $this->requestUser;
        }

        throw new GetUserFromRequestTraitException(
            'GetUserFromRequest::getUser | User not found in request'
        );
    }


    protected function getRequestUserOrNull(): ?User
    {
        if (!request()->has('user')) {
            return null;
        }
        $requestUser = request()->input('user');
        $this->setRequestUser($requestUser);
        return $this->requestUser;
    }


    protected function loggedUserIsSuperUser(): bool
    {
        return request()->jwtPayload['is_super_user'] ?? false;
    }


    public function setRequestUser(User $requestUser)
    {
        $this->requestUser = $requestUser;
    }

}
