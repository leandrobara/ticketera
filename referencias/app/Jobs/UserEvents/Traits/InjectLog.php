<?php

namespace App\Jobs\UserEvents\Traits;

use Illuminate\Support\Facades\Log;


trait InjectLog
{

    public function getInfoLog()
    {
        // Deshardcodear.
        return Log::channel('user_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('user_events_error');
    }
}
