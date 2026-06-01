<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\WhatsAppAttachment;


class WhatsAppAttachmentObserver
{

    public function deleted(WhatsAppAttachment $wapAttachment)
    {
        $wapAttachment->deleted_at_ts = Carbon::now()->timestamp;
        $wapAttachment->save();
    }

}
