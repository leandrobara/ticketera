<?php

namespace App\Events;

use DateTime;
use Exception;
use DateTimeZone;
use Carbon\Carbon;
use App\Models\Email;
use Illuminate\Queue\SerializesModels;
use App\Services\API\Views\EmailService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class ReOpenedProposalEvent implements ShouldBroadcast
{

    use InteractsWithSockets, SerializesModels;

    public $tries = 2;
    public $backoff = 20;
    
    public $queue;
    public $email;
    public $emailId;


    public function __construct(int $emailId)
    {
        $this->emailId = $emailId;
        $this->queue = config('queue.browser_events');
    }


    public function broadcastWhen()
    {
        $this->email = $this->email ?? Email::findOrFail($this->emailId);
        if (!$this->email || !$this->email->is_proposal || !$this->email->sent_date || !$this->email->opened_date) {
            return false;
        }
        if (!$this->email->lead || !$this->email->client) {
            return false;
        }
        return true;
    }


    public function broadcastWith()
    {
        $this->email = $this->email ?? Email::findOrFail($this->emailId);
        $email = resolve(EmailService::class)->fillEmailWithMailerInfo($this->email);

        $dateTimeZone = new DateTimeZone($email->client->timezone);
        $dateNow = (new DateTime('now'))->setTimezone($dateTimeZone);
        $emailSentDate = $email->sent_date->setTimezone($dateTimeZone);
        $daysSinceReopening = $dateNow->diff($emailSentDate)->format("%a");

        $data = [
            'lead' => [
                'id' => $email->lead->id,
                'userId' => $email->lead->user_id,
                'companyName' => $email->lead->company,
                'leadName' => $email->lead->mainFullName,
                'leadPhone' => $email->lead->mainPhone,
                'leadEmail' => $email->lead->mainLeadContact->leadContactEmails->first()->email,
            ],
            'email' => [
                'id' => $email->id,
                'daysSinceReopening' => $daysSinceReopening,
                'sentDate' => $emailSentDate->format('d/m/Y'),
                'subject' => $email->getMailerDTO()->get('subject'),
            ],
        ];
        return $data;
    }


    public function broadcastOn()
    {
        $this->email = $this->email ?? Email::findOrFail($this->emailId);
        return new PrivateChannel('Browser.Notifications.Channel.Client.' . $this->email->client_id);
    }


    public function broadcastAs()
    {
        return 'ReOpenedProposalEvent';
    }

}
