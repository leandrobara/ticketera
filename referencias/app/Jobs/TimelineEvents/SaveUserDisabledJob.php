<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Services\API\EventsLogService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveUserDisabledJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;


    public function __construct(
        private readonly int $loginUserId,
        private readonly int $disabledUserId,
        private readonly DateTime $eventLogDate
    ) {
    }


    public function handle()
    {
        $loginUser = User::findOrFail($this->loginUserId);
        $disabledUser = User::findOrFail($this->disabledUserId);
        resolve(EventsLogService::class)->saveUserDisabled($loginUser, $disabledUser, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }

}
