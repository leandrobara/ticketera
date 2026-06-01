<?php

namespace App\Events;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class TaskMassiveUserChangeEvent implements ShouldBroadcast
{

    use InteractsWithSockets, SerializesModels;

    public $tries = 2;
    public $backoff = 20;
    
    public $queue;
    public $taskIds;
    public $newUser;
    public $assignerUser;
    public $enableTaskUserChangeBrowserAlert;


    public function __construct(array $taskIds, int $assignerUserId, int $newUserId)
    {
        $this->taskIds = $taskIds;
        $this->newUserId = $newUserId;
        $this->queue = config('queue.browser_events');
        
        $this->newUser = User::find($newUserId);
        $this->assignerUser = User::find($assignerUserId);
        $clientSettings = $this->newUser->client->clientSettings;
        $this->enableTaskUserChangeBrowserAlert = $clientSettings->enable_task_user_change_browser_alert;
    }


    public function broadcastWhen()
    {
        return $this->enableTaskUserChangeBrowserAlert && $this->newUser && $this->taskIds;
    }


    public function broadcastWith()
    {
        if (!$this->enableTaskUserChangeBrowserAlert || !$this->newUser || !$this->assignerUser || !$this->taskIds) {
            return [];
        }
        return [
            'taskCount' => count($this->taskIds),
            'newUser' => $this->newUser->only(['id', 'name', 'last_name', 'email']),
            'assignerUser' => $this->assignerUser->only(['id', 'name', 'last_name', 'email']),
        ];
    }


    public function broadcastOn()
    {
        return new PrivateChannel('Browser.Notifications.Channel.Client.' . $this->newUser->client_id);
    }


    public function broadcastAs()
    {
        return 'TaskMassiveUserChangeEvent';
    }
}
