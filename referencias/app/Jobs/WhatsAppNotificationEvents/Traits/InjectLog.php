<?php

namespace App\Jobs\WhatsAppNotificationEvents\Traits;

use Illuminate\Support\Facades\Log;


trait InjectLog
{

    public function getInfoLog()
    {
        return Log::channel('whatsapp_notification_events_info');
    }

    public function getErrorLog()
    {
        return Log::channel('whatsapp_notification_events_error');
    }

}
