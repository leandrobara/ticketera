<?php

namespace App\Jobs\WhatsAppEvents\Traits;

use Illuminate\Support\Facades\Log;


trait InjectWapSenderLog
{

    public function getInfoLog()
    {
        return Log::channel('whatsapp_sender_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('whatsapp_sender_events_errors');
    }

}
