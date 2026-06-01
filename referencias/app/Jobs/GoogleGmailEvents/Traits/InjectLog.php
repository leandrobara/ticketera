<?php

namespace App\Jobs\GoogleGmailEvents\Traits;

use Illuminate\Support\Facades\Log;


trait InjectLog
{

    public function getInfoLog()
    {
        return Log::channel('google_gmail_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('google_gmail_events_error');
    }

}
