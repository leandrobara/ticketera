<?php

namespace App\Jobs\IntegrationAPIEvents\Traits;

use Illuminate\Support\Facades\Log;


trait InjectLog
{

    public function getInfoLog()
    {
        // Deshardcodear.
        return Log::channel('integration_api_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('integration_api_events_error');
    }
}
