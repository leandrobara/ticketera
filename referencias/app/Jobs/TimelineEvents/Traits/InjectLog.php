<?php

namespace App\Jobs\TimelineEvents\Traits;

use Illuminate\Support\Facades\Log;

trait InjectLog
{

    public function getInfoLog()
    {
        // Deshardcodear.
        return Log::channel('timeline_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('timeline_events_error');
    }
}
