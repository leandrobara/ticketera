<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\User;
use App\Models\LeadSale;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use App\Services\API\EventsLogService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadSaleDeletedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $userId;
    public $leadSaleId;
    public $eventLogDate;
    

    public function __construct(?int $userId, int $leadSaleId, ?DateTime $eventLogDate = null)
    {
        $this->userId = $userId;
        $this->leadSaleId = $leadSaleId;
        $this->eventLogDate = $eventLogDate;
    }


    public function handle()
    {
        $leadSale = LeadSale::withTrashed()->findOrFail($this->leadSaleId);
        $user = $this->userId ? resolve(UserService::class)->findOrFail($this->userId) : null;
        resolve(EventsLogService::class)->saveLeadSaleDeleted($user, $leadSale, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
