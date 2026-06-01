<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use App\Services\API\EventsLogService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadCreatedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;


    public function __construct(public int $leadId, public ?DateTime $eventLogDate = null)
    {
    }

    public function handle()
    {
        $lead = Lead::findOrFail($this->leadId);
        resolve(EventsLogService::class)->saveLeadCreated($lead, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
