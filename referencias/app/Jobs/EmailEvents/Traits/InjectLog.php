<?php

namespace App\Jobs\EmailEvents\Traits;

use Illuminate\Support\Facades\Log;


trait InjectLog
{

    public function getInfoLog()
    {
        return Log::channel('email_events_info');
    }


    public function getErrorLog()
    {
        return Log::channel('email_events_error');
    }

}
