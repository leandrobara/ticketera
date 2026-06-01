<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\Task;
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


class SaveLeadTaskUpdatedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    

    public function __construct(
        public int $userId,
        public array $oldTask,
        public int $newTaskId,
        public ?DateTime $eventLogDate,
    ) {
    }


    public function handle()
    {
        $newTask = Task::findOrFail($this->newTaskId);
        $user = resolve(UserService::class)->findOrFail($this->userId);

        resolve(EventsLogService::class)->saveLeadTaskUpdated($user, $this->oldTask, $newTask, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
