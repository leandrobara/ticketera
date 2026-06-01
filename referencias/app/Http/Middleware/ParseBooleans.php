<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;


class ParseBooleans extends TransformsRequest
{
    protected function transform($key, $value)
    {
        if (strtolower(trim($value)) === 'true') {
            return true;
        }
        if (strtolower(trim($value)) === 'false') {
            return false;
        }
        return $value;
    }
}