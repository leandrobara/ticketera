<?php

namespace App\Jobs\TaskEvents;

use Throwable;
use Exception;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\TaskNotificationEmailService;


class SendNewTaskEmailJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $taskId;
    public $assignerUserId;
    

    public function __construct(int $taskId, int $assignerUserId)
    {
        $this->taskId = $taskId;
        $this->assignerUserId = $assignerUserId;
    }


    public function handle()
    {
        $task = Task::findOrFail($this->taskId);
        $assignerUser = resolve(UserService::class)->findOrFail($this->assignerUserId);
        $service = resolve(TaskNotificationEmailService::class);
        $taskNotificationEmail = $service->sendNewTaskNotificationEmailToUser($task, $assignerUser);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
