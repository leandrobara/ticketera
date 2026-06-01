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
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadQualityUpdatedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    
    public function __construct(
        public ?int $userId,
        public int $leadId,
        public ?int $oldQuality,
        public ?int $newQuality,
        public ?DateTime $eventLogDate = null
    ) {
    }


    public function handle()
    {
        $lead = Lead::findOrFail($this->leadId);
        $user = $this->userId ? resolve(UserService::class)->findOrFail($this->userId) : null;
        resolve(EventsLogService::class)->saveLeadQualityUpdated(
            $user, $lead, $this->oldQuality, $this->newQuality, $this->eventLogDate
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
