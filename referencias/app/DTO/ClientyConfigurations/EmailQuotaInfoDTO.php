<?php

namespace App\DTO\ClientyConfigurations;

use Illuminate\Support\Collection;


class EmailQuotaInfoDTO
{

    public $dailyQuota = null;
    public $dailyUsedQuota = null;
    public $availableDailyQuota = null;
    
    public $monthlyQuota = null;
    public $monthlyUsedQuota = null;
    public $availableMonthlyQuota = null;

}