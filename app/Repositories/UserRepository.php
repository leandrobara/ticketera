<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', mb_strtolower(trim($email)))->first();
    }
}
