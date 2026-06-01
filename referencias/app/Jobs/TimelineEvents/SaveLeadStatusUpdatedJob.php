<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\Lead;
use App\Models\User;
use App\Models\Status;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use App\Services\API\EventsLogService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadStatusUpdatedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;


    public function __construct(
        public ?int $userId,
        public int $leadId,
        public array $oldStatus,
        public int $newStatusId,
        public ?DateTime $eventLogDate = null
    ) {
    }


    public function handle()
    {
        $lead = Lead::findOrFail($this->leadId);
        $newStatus = Status::findOrFail($this->newStatusId);
        $user = $this->userId ? resolve(UserService::class)->findOrFail($this->userId) : null;

        resolve(EventsLogService::class)->saveLeadStatusUpdated(
            $user, $lead, $newStatus, $this->oldStatus, $this->eventLogDate
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
