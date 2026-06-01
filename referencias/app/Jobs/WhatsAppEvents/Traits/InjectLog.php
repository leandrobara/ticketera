<?php

namespace App\Jobs\WhatsAppEvents\Traits;

use Illuminate\Support\Facades\Log;

trait InjectLog
{

    public function getInfoLog()
    {
        return Log::channel('whatsapp_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('whatsapp_events_error');
    }

}
