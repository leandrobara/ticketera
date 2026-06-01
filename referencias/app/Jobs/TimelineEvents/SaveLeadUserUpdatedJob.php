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


class SaveLeadUserUpdatedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $leadId;
    public $oldUser;
    public $newUserId;
    public $loginUserId;
    public $eventLogDate;

    
    public function __construct(
        int $leadId,
        array $oldUser,
        int $newUserId,
        ?int $loginUserId,
        ?DateTime $eventLogDate
    ) {
        $this->leadId = $leadId;
        $this->oldUser = $oldUser;
        $this->newUserId = $newUserId;
        $this->loginUserId = $loginUserId;
        $this->eventLogDate = $eventLogDate;
    }


    public function handle()
    {
        $lead = Lead::findOrFail($this->leadId);
        $newUser = resolve(UserService::class)->findOrFail($this->newUserId);
        $loginUser = $this->loginUserId ? resolve(UserService::class)->findOrFail($this->loginUserId) : null;
        resolve(EventsLogService::class)->saveLeadUserUpdated(
            $this->oldUser, $newUser, $lead, $loginUser, $this->eventLogDate
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
