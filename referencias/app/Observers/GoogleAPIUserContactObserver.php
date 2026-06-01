<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\GoogleAPIUserContact;


class GoogleAPIUserContactObserver
{
    
    public function deleted(GoogleAPIUserContact $userContact)
    {
        $userContact->deleted_at_ts = Carbon::now()->timestamp;
        $userContact->save();
    }

}
