<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\Lead;
use App\Models\User;
use App\Models\Email;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use App\Services\API\EventsLogService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadEmailScheduledJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $leadId;
    public $userId;
    public $emailId;
    public $eventLogDate;
    

    public function __construct(int $userId, int $emailId, int $leadId, ?DateTime $eventLogDate = null)
    {
        $this->leadId = $leadId;
        $this->userId = $userId;
        $this->emailId = $emailId;
        $this->eventLogDate = $eventLogDate;
    }


    public function handle()
    {
        $lead = Lead::findOrFail($this->leadId);
        $email = Email::findOrFail($this->emailId);
        $user = resolve(UserService::class)->findOrFail($this->userId);

        resolve(EventsLogService::class)->saveLeadEmailScheduled($user, $email, $lead, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
