<?php

namespace App\Jobs\ElasticLeadEvents\Traits;

use Illuminate\Support\Facades\Log;


trait InjectLog
{

    public function getInfoLog()
    {
        // Deshardcodear.
        return Log::channel('elastic_lead_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('elastic_lead_events_error');
    }
}
