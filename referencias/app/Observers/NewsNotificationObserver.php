<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\NewsNotification;


class NewsNotificationObserver
{

    public function deleted(NewsNotification $newsNotification)
    {
        $newsNotification->deleted_at_ts = Carbon::now()->timestamp;
        $newsNotification->save();
    }

}
