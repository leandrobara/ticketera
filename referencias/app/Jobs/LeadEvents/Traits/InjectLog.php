<?php

namespace App\Jobs\LeadEvents\Traits;

use Illuminate\Support\Facades\Log;


trait InjectLog
{

    public function getInfoLog()
    {
        // Deshardcodear.
        return Log::channel('lead_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('lead_events_error');
    }
}
