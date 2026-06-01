<?php

namespace App\Jobs\WhatsAppEvents\Traits;

use Illuminate\Support\Facades\Log;

trait InjectWAutomationLog
{

    public function getInfoLog()
    {
        return Log::channel('whatsapp_automation_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('whatsapp_automation_events_error');
    }

}
