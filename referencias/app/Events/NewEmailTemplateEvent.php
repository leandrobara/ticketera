<?php

namespace App\Events;

use App\Models\EmailTemplate;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class NewEmailTemplateEvent implements ShouldBroadcast
{

    use InteractsWithSockets, SerializesModels;

    public $tries = 2;
    public $backoff = 20;
    
    public $queue;
    public $emailTemplate;
    public $emailTemplateId;


    public function __construct(int $emailTemplateId)
    {
        $this->emailTemplateId = $emailTemplateId;
        $this->queue = config('queue.browser_events');
    }


    public function broadcastWhen()
    {
        return true;
    }


    public function broadcastWith()
    {
        $this->emailTemplate = $this->emailTemplate ?? EmailTemplate::findOrFail($this->emailTemplateId);
        // Only id, because template data generally exceeds maximum allowed Pusher event data size.
        return ['id' => $this->emailTemplate->id];
    }


    public function broadcastOn()
    {
        $this->emailTemplate = $this->emailTemplate ?? EmailTemplate::findOrFail($this->emailTemplateId);
        return new PrivateChannel('Browser.Notifications.Channel.Client.' . $this->emailTemplate->client_id);
    }


    public function broadcastAs()
    {
        return 'NewEmailTemplateEvent';
    }

}
