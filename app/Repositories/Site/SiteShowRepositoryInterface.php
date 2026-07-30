<?php

namespace App\Repositories\Site;

use App\Models\Show;

interface SiteShowRepositoryInterface
{
    public function getPublicShow(Show $show): Show;
}
