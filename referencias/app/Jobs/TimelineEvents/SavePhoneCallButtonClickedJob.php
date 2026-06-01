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


class SavePhoneCallButtonClickedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    

    public function __construct(
        public int $userId,
        public int $leadId,
        public string $phoneNumber,
        public ?DateTime $eventLogDate = null
    ) {
    }


    public function handle()
    {
        $lead = Lead::findOrFail($this->leadId);
        $user = resolve(UserService::class)->findOrFail($this->userId);
        resolve(EventsLogService::class)->savePhoneCallButtonClicked(
            $user, $lead, $this->phoneNumber, $this->eventLogDate
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
