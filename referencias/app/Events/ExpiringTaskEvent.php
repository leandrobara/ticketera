<?php

namespace App\Events;

use Carbon\Carbon;
use App\Models\Task;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class ExpiringTaskEvent implements ShouldBroadcast
{

    use InteractsWithSockets, SerializesModels;

    public $tries = 2;
    public $backoff = 20;

    public $task;
    public $queue;
    public $taskId;


    public function __construct(int $taskId)
    {
        $this->taskId = $taskId;
        $this->queue = config('queue.browser_events');
    }


    public function broadcastWith()
    {
        $this->task = $this->task ?? Task::findOrFail($this->taskId);
        return [
            'id' => $this->task->id,
            'title' => $this->task->title,
            'userId' => $this->task->user_id,
            'leadId' => $this->task->lead->id,
            'companyName' => $this->task->lead->company,
            'leadName' => $this->task->lead->mainFullName,
            'expirationDate' => Carbon::parse($this->task->limit_date)->format('d/m/Y'),
        ];
    }


    public function broadcastWhen()
    {
        $this->task = $this->task ?? Task::findOrFail($this->taskId);
        return $this->task->client->clientSettings->enable_task_hour_reminder_browser_alert;
    }


    public function broadcastOn()
    {
        $this->task = $this->task ?? Task::findOrFail($this->taskId);
        return new PrivateChannel('Browser.Notifications.Channel.Client.' . $this->task->client_id);
    }


    public function broadcastAs()
    {
        return 'ExpiringTaskEvent';
    }

}
