<?php

namespace App\DTO;

use App\Models\User;


class SuperUserAuthDTO
{
    
    public function __construct(public readonly User $superUser, public readonly ?User $loginUser = null)
    {
    }
   
}
