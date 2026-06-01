<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class NewLeadEvent implements ShouldBroadcast
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


    public function broadcastWhen()
    {
        $this->lead = $this->lead ?? Lead::find($this->leadId);
        if (!$this->lead) {
            return false;
        }
        return $this->lead->client->clientSettings->enable_new_lead_browser_alert;
    }


    public function broadcastWith()
    {
        $this->lead = $this->lead ?? Lead::find($this->leadId);
        if (!$this->lead) {
            return [];
        }

        $user = $this->lead->user;
        $contact = $this->lead->mainLeadContact;
        if (!$contact) {
            return [];
        }
        $leadContactEmail = $this->lead->mainLeadContact->leadContactEmails->first();

        return [
            'id' => $this->lead->id,
            'userId' => $this->lead->user_id,
            'userName' => $user->name . ' ' . $user->last_name,
            'name' => $contact->name . ' ' . $contact->last_name,
            'email' => $leadContactEmail ? $leadContactEmail->email : '',
        ];
    }


    public function broadcastOn()
    {
        $this->lead = $this->lead ?? Lead::findOrFail($this->leadId);
        return new PrivateChannel('Browser.Notifications.Channel.Client.' . $this->lead->client_id);
    }


    public function broadcastAs()
    {
        return 'NewLeadEvent';
    }

}
