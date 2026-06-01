<?php

namespace App\Jobs\ClientEvents\Traits;

use Illuminate\Support\Facades\Log;


trait InjectLog
{

    public function getInfoLog()
    {
        return Log::channel('client_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('client_events_error');
    }

}
