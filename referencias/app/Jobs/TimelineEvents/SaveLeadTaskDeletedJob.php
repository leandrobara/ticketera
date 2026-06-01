<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use App\Services\API\EventsLogService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadTaskDeletedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $userId;
    public $taskId;
    public $eventLogDate;


    public function __construct(int $userId, int $taskId, ?DateTime $eventLogDate = null)
    {
        $this->userId = $userId;
        $this->taskId = $taskId;
        $this->eventLogDate = $eventLogDate;
    }


    public function handle()
    {
        $task = Task::withTrashed()->findOrFail($this->taskId);
        $user = resolve(UserService::class)->findOrFail($this->userId);
        resolve(EventsLogService::class)->saveLeadTaskDeleted($user, $task, $this->eventLogDate);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
