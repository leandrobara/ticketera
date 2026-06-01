<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use App\Models\AcquisitionChannel;
use App\Services\API\EventsLogService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadAcquisitionChannelUpdatedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $userId;
    public $leadId;
    public $eventLogDate;
    public $oldAcquisitionChannel;
    public $newAcquisitionChannelId;


    public function __construct(
        ?int $userId,
        int $leadId,
        ?array $oldAcquisitionChannel,
        int $newAcquisitionChannelId,
        ?DateTime $eventLogDate = null
    ) {
        $this->leadId = $leadId;
        $this->userId = $userId;
        $this->eventLogDate = $eventLogDate;
        $this->oldAcquisitionChannel = $oldAcquisitionChannel;
        $this->newAcquisitionChannelId = $newAcquisitionChannelId;
    }


    public function handle()
    {
        $lead = Lead::findOrFail($this->leadId);
        $user = $this->userId ? resolve(UserService::class)->findOrFail($this->userId) : null;
        $newChannel = AcquisitionChannel::findOrFail($this->newAcquisitionChannelId);

        resolve(EventsLogService::class)->saveLeadAcquisitionChannelUpdated(
            $user, $lead, $newChannel, $this->oldAcquisitionChannel, $this->eventLogDate
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
