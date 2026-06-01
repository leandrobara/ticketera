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


class SaveLeadSaleUpdatedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $userId;
    public $oldLeadSale;
    public $eventLogDate;
    public $newLeadSaleId;
    public $oldLeadSaleUserId;


    public function __construct(
        ?int $userId,
        array $oldLeadSale,
        int $oldLeadSaleUserId,
        int $newLeadSaleId,
        ?DateTime $eventLogDate = null
    ) {
        $this->userId = $userId;
        $this->oldLeadSale = $oldLeadSale;
        $this->eventLogDate = $eventLogDate;
        $this->newLeadSaleId = $newLeadSaleId;
        $this->oldLeadSaleUserId = $oldLeadSaleUserId;
    }


    public function handle()
    {
        $newLeadSale = LeadSale::findOrFail($this->newLeadSaleId);
        $oldLeadSaleUser = resolve(UserService::class)->findOrFail($this->oldLeadSaleUserId);
        $user = $this->userId ? resolve(UserService::class)->findOrFail($this->userId) : null;

        resolve(EventsLogService::class)->saveLeadSaleUpdated(
            $user, $this->oldLeadSale, $oldLeadSaleUser, $newLeadSale, $this->eventLogDate
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
