<?php

namespace App\Events;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Task;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class NewTaskEvent implements ShouldBroadcast
{

    use InteractsWithSockets, SerializesModels;

    public $tries = 2;
    public $backoff = 20;
    
    public $task;
    public $queue;
    public $assignerUser;
    public $enableNewTaskBrowserAlert;


    public function __construct(int $taskId, int $assignerUserId)
    {
        $this->task = Task::find($taskId);
        $this->assignerUser = User::find($assignerUserId);
        $this->queue = config('queue.browser_events');
        $this->enableNewTaskBrowserAlert = $this->task?->client?->clientSettings?->enable_new_task_browser_alert;
    }


    public function broadcastWhen()
    {
        return $this->enableNewTaskBrowserAlert && $this->task && $this->task->user;
    }


    public function broadcastWith()
    {
        if (!$this->enableNewTaskBrowserAlert || !$this->task || !$this->task->user) {
            return [];
        }

        return [
            'id' => $this->task->id,
            'title' => $this->task->title,
            'userId' => $this->task->user_id,
            'userName' => $this->task->user->full_name,
            'limitDate' => $this->task->clientTimezoneLimitDate,
            'assignerUserName' => $this->assignerUser?->full_name ?? '',
            'expirationDate' => $this->task->clientTimezoneLimitDate->format('d/m/Y'),
        ];
    }


    public function broadcastOn()
    {
        return new PrivateChannel('Browser.Notifications.Channel.Client.' . $this->task->client_id);
    }


    public function broadcastAs()
    {
        return 'NewTaskEvent';
    }

}
