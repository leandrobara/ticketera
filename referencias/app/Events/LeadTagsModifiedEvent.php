<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class LeadTagsModifiedEvent implements ShouldBroadcast
{

    use InteractsWithSockets, SerializesModels;

    public $tries = 2;
    public $backoff = 20;
    
    public $lead;
    public $queue;
    public $leadId;


    public function __construct(int $leadId)
    {
        $this->leadId = $leadId;
        $this->queue = config('queue.browser_events');
    }


    public function broadcastWith()
    {
        $this->lead = $this->lead ?? Lead::findOrFail($this->leadId);
        return [
            'lead' => ['id' => $this->lead->id],
            'tags' => $this->lead->tags->toArray(),
        ];
    }


    public function broadcastOn()
    {
        $this->lead = $this->lead ?? Lead::findOrFail($this->leadId);
        return new PrivateChannel('Browser.Notifications.Channel.Client.' . $this->lead->client_id);
    }


    public function broadcastAs()
    {
        return 'LeadTagsModifiedEvent';
    }

}
