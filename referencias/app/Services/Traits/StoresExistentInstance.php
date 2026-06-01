<?php

namespace App\Services\Traits;

use App\Models\User;
use App\Exceptions\Services\Traits\GetUserFromRequestTraitException;


trait StoresExistentInstance
{
    
    protected static $instance = null;

    
    protected function setExistentInstance($instance)
    {
        self::$instance = $instance;
    }


    public static function getExistentInstance()
    {
        return self::$instance;
    }

}
