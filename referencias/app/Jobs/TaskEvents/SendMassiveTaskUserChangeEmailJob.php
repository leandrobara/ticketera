<?php

namespace App\Jobs\TaskEvents;

use Throwable;
use Exception;
use App\Models\User;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use App\Services\API\TaskService;
use App\Models\TaskNotificationEmail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\TaskNotificationEmailService;


class SendMassiveTaskUserChangeEmailJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $taskNotificationEmailId;


    public function __construct(int $taskNotificationEmailId)
    {
        $this->taskNotificationEmailId = $taskNotificationEmailId;
    }


    public function handle()
    {
        $taskNotificationEmail = TaskNotificationEmail::findOrFail($this->taskNotificationEmailId);
        
        $taskIds = $taskNotificationEmail->massive_user_change_task_ids;
        $tasks = resolve(TaskService::class)->findByClientAndIds($taskNotificationEmail->client, $taskIds);
        
        $assignedUser = $tasks->first()->user;
        resolve(TaskNotificationEmailService::class)->sendMassiveTaskUserChangeNotificationEmailToAssignedUser(
            $taskNotificationEmail, $assignedUser
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
