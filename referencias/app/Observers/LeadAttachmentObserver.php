<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\LeadAttachment;


class LeadAttachmentObserver
{

    public function deleted(LeadAttachment $leadAttachment)
    {
        $leadAttachment->deleted_at_ts = Carbon::now()->timestamp;
        $leadAttachment->save();
    }

}
