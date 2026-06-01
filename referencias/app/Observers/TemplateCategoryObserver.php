<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\TemplateCategory;


class TemplateCategoryObserver
{

    public function deleted(TemplateCategory $templateCategory)
    {
        $templateCategory->deleted_at_ts = Carbon::now()->timestamp;
        $templateCategory->save();
    }

}
