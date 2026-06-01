<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use App\Services\API\EventsLogService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadManuallyCreatedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $leadId;
    public $userId;
    public $eventLogDate;
    

    public function __construct(int $userId, int $leadId, ?DateTime $eventLogDate = null)
    {
        $this->leadId = $leadId;
        $this->userId = $userId;
        $this->eventLogDate = $eventLogDate;
    }


    public function handle()
    {
        $lead = Lead::find($this->leadId);
        if (!$lead) {
            return true;
        }
        $user = resolve(UserService::class)->findOrFail($this->userId);
        resolve(EventsLogService::class)->saveLeadManuallyCreated($user, $lead, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
