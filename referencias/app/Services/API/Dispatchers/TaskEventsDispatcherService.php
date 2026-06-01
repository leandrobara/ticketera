<?php

namespace App\Services\API\Dispatchers;

use DateTime;
use Exception;
use App\Models\User;
use App\Models\Task;
use Illuminate\Support\Collection;
use App\Models\TaskNotificationEmail;
use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\CustomDispatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\TaskEvents\SendNewTaskEmailJob;
use App\Jobs\TaskEvents\MassiveTaskUserChangeJob;
use App\Jobs\TaskEvents\SendTaskUserChangeEmailJob;
use App\Jobs\TaskEvents\SendNewTaskWhatsAppMessageJob;
use App\Jobs\TaskEvents\SendMassiveTaskUserChangeEmailJob;
use App\Jobs\TaskEvents\SendTaskUserChangeWhatsAppMessageJob;


class TaskEventsDispatcherService
{

    use CustomDispatch;
    
    private $eventQueueName;
    private $queueConnection;


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function dispatchSendNewTaskEmailJob(Task $task, User $assignerUser, ?int $delaySecs = 15)
    {
        $params = [$task->id, $assignerUser->id];
        $this->doCustomDispatch(SendNewTaskEmailJob::class, $params, $delaySecs, $task->client_id);
    }


    public function dispatchSendTaskUserChangeEmailJob(Task $task, User $assignerUser, int $oldTaskUserId)
    {
        $params = [$task->id, $oldTaskUserId, $assignerUser->id];
        $this->doCustomDispatch(SendTaskUserChangeEmailJob::class, $params, 15, $task->client_id);
    }


    public function dispatchSendMassiveTaskUserChangeEmailJob(
        TaskNotificationEmail $taskNotificationEmail,
        ?int $delaySecs = 15
    ) {
        $params = [$taskNotificationEmail->id];
        $this->doCustomDispatch(
            SendMassiveTaskUserChangeEmailJob::class, $params, $delaySecs, $taskNotificationEmail->client_id
        );
    }


    public function dispatchSendNewTaskWhatsAppMessageJob(Task $task, User $assignerUser, ?int $delaySecs = 15)
    {
        $params = [$task->id, $assignerUser->id];
        $this->doCustomDispatch(SendNewTaskWhatsAppMessageJob::class, $params, $delaySecs, $task->client_id);
    }


    public function dispatchSendTaskUserChangeWhatsAppMessageJob(Task $task, User $assignerUser, int $oldTaskUserId)
    {
        $params = [$task->id, $oldTaskUserId, $assignerUser->id];
        $this->doCustomDispatch(SendTaskUserChangeWhatsAppMessageJob::class, $params, 15, $task->client_id);
    }


    public function dispatchMassiveTaskUserChangeJob(Collection $taskIds, User $newUser)
    {
        $this->doCustomDispatch(
            MassiveTaskUserChangeJob::class, [$taskIds->toArray(), $newUser->id], null, $newUser->client_id
        );
    }

}
