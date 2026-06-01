<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\User;
use App\Models\LeadSale;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Support\Collection;
use Illuminate\Queue\SerializesModels;
use App\Services\API\EventsLogService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadMassiveDeletedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $ip;
    public $userId;
    public $leadIds;
    public $eventLogDate;
    

    public function __construct(int $userId, array $leadIds, string $ip, ?DateTime $eventLogDate = null)
    {
        $this->ip = $ip;
        $this->userId = $userId;
        $this->leadIds = $leadIds;
        $this->eventLogDate = $eventLogDate;
    }


    public function handle()
    {
        if (!$this->leadIds) {
            return null;
        }
        $user = resolve(UserService::class)->findOrFail($this->userId);
        resolve(EventsLogService::class)->saveLeadMassiveDeletion(
            $user, $this->leadIds, $this->ip, $this->eventLogDate
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
