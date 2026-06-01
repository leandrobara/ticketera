<?php

namespace App\Jobs\GoogleContactsEvents\Traits;

use Illuminate\Support\Facades\Log;


trait InjectLog
{

    public function getInfoLog()
    {
        // Deshardcodear.
        return Log::channel('google_contacts_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('google_contacts_events_error');
    }
}
