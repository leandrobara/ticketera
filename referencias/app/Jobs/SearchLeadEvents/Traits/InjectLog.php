<?php

namespace App\Jobs\SearchLeadEvents\Traits;

use Illuminate\Support\Facades\Log;


trait InjectLog
{

    public function getInfoLog()
    {
        // Deshardcodear.
        return Log::channel('search_lead_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('search_lead_events_error');
    }
}
