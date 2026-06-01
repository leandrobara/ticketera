<?php

namespace App\Repositories;

use App\Models\UserLogin;
use Exception;

class UserLoginRepository
{
    public function create($data)
    {
        $login = new UserLogin($data);
        $login->saveOrFail();
        return $login->fresh();
    }
}
