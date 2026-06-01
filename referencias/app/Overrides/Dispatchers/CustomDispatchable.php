<?php

namespace App\Overrides\Dispatchers;

use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomPendingDispatch;


trait CustomDispatchable
{

    use Dispatchable;


    /**
     * @Override
     */
    public static function dispatch()
    {
        return new CustomPendingDispatch(new static(...func_get_args()));
    }

}
