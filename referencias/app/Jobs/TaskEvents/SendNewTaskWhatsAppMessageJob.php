<?php

namespace App\Jobs\TaskEvents;

use Throwable;
use Exception;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\TaskNotificationWhatsAppMessageService;


class SendNewTaskWhatsAppMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public int $taskId;
    public int $assignerUserId;
    

    public function __construct(int $taskId, int $assignerUserId)
    {
        $this->taskId = $taskId;
        $this->assignerUserId = $assignerUserId;
    }


    public function handle()
    {
        $task = Task::findOrFail($this->taskId);
        $assignerUser = resolve(UserService::class)->findOrFail($this->assignerUserId);
        $service = resolve(TaskNotificationWhatsAppMessageService::class);
        $service->sendNewTaskWhatsAppMessageNotificationToTaskUser($task, $assignerUser);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
