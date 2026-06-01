<?php

namespace App\Events;

use Carbon\Carbon;
use App\Models\Task;
use App\Models\User;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class TaskUserChangeEvent implements ShouldBroadcast
{

    use InteractsWithSockets, SerializesModels;

    public $tries = 2;
    public $backoff = 20;
    
    public $task;
    public $queue;
    public $assignerUser;
    public $enableTaskUserChangeBrowserAlert;


    public function __construct(int $taskId, int $assignerUserId)
    {
        $this->task = Task::find($taskId);
        $this->queue = config('queue.browser_events');
        $this->assignerUser = User::find($assignerUserId);
        
        $clientSettings = $this->task?->client?->clientSettings;
        $this->enableTaskUserChangeBrowserAlert = $clientSettings?->enable_task_user_change_browser_alert;
    }


    public function broadcastWhen()
    {
        return $this->enableTaskUserChangeBrowserAlert && $this->task && $this->task->user;
    }


    public function broadcastWith()
    {
        if (!$this->enableTaskUserChangeBrowserAlert || !$this->task || !$this->task->user) {
            return [];
        }

        return [
            'id' => $this->task->id,
            'title' => $this->task->title,
            'userId' => $this->task->user_id,
            'userName' => $this->task->user->full_name,
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
        return 'TaskUserChangeEvent';
    }
}
