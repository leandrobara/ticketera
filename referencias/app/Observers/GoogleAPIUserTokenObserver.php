<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\GoogleAPIUserToken;


class GoogleAPIUserTokenObserver
{
    
    public function deleted(GoogleAPIUserToken $userToken)
    {
        $userToken->deleted_at_ts = Carbon::now()->timestamp;
        $userToken->save();
    }

}
