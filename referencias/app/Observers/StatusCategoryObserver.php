<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\StatusCategory;


class StatusCategoryObserver
{

    public function deleted(StatusCategory $statusCategory)
    {
        $statusCategory->deleted_at_ts = Carbon::now()->timestamp;
        $statusCategory->save();
    }

}
