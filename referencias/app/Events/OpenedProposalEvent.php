<?php

namespace App\Events;

use Carbon\Carbon;
use App\Models\Email;
use Illuminate\Queue\SerializesModels;
use App\Services\API\Views\EmailService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class OpenedProposalEvent implements ShouldBroadcast
{

    use InteractsWithSockets, SerializesModels;

    public $tries = 2;
    public $backoff = 20;
    
    public $email;
    public $queue;
    public $emailId;


    public function __construct(int $emailId)
    {
        $this->emailId = $emailId;
        $this->queue = config('queue.browser_events');
    }


    public function broadcastWhen()
    {
        $this->email = $this->email ?? Email::findOrFail($this->emailId);
        return $this->email->client->clientSettings->enable_lead_proposal_interaction_browser_alert;
    }


    public function broadcastWith()
    {
        $this->email = $this->email ?? Email::findOrFail($this->emailId);
        if (!$this->email->lead) {
            return [];
        }
        $email = resolve(EmailService::class)->fillEmailWithMailerInfo($this->email);

        $contactDataStr = 'Nombre: ' . $email->lead->mainFullName;
        $leadContactEmail = $email->lead->mainLeadContact->leadContactEmails->first();
        if ($leadContactEmail) {
            $contactDataStr .= ' - Email: ' . $leadContactEmail->email;
        }
        if ($email->lead->mainPhone) {
            $contactDataStr .= ' - Teléfono: ' . $email->lead->mainPhone;
        }

        return [
            'lead' => [
                'id' => $email->lead->id,
                'contactData' => $contactDataStr,
                'userId' => $email->lead->user_id,
                'nameOrEmail' => $email->lead->mainFullName,
            ],
            'email' => [
                'id' => $email->id,
                'subject' => $email->getMailerDTO()->get('subject'),
                'sentDate' => Carbon::parse($email->sent_date)->format('d/m/Y'),
            ],
        ];
    }


    public function broadcastOn()
    {
        $this->email = $this->email ?? Email::findOrFail($this->emailId);
        return new PrivateChannel('Browser.Notifications.Channel.Client.' . $this->email->client_id);
    }


    public function broadcastAs()
    {
        return 'OpenedProposalEvent';
    }

}
