<?php

namespace App\Helpers;

class LeadHashHelper
{
    public function create(string $name, string $lastName, string $message)
    {
        return md5($name . $lastName . $message);
    }
}
