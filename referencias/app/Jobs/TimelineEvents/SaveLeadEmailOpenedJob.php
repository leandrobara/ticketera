<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\Lead;
use App\Models\User;
use App\Models\Email;
use Illuminate\Bus\Queueable;
use App\Services\API\EventsLogService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadEmailOpenedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $emailId;
    public $eventLogDate;


    public function __construct(int $emailId, ?DateTime $eventLogDate = null)
    {
        $this->emailId = $emailId;
        $this->eventLogDate = $eventLogDate;
    }


    public function handle()
    {
        $email = Email::findOrFail($this->emailId);
        if (!$email->lead) {
            return null;
        }
        resolve(EventsLogService::class)->saveLeadEmailOpened($email, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }

}
