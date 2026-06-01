<?php

namespace App\Services\API\Dispatchers;

use App\Models\User;
use App\Models\Email;
use App\Models\Client;
use App\Models\AutomationTask;
use Illuminate\Support\Collection;
use App\Models\AutomationNewLead;
use App\Models\AutomationProposal;
use App\Models\WAutomationProposal;
use App\Models\AutomationEmailSend;
use App\Models\WAutomationSequence;
use App\Models\WAutomationAfterSend;
use App\Jobs\EmailEvents\EmailSentJob;
use App\Services\Traits\CustomDispatch;
use App\Jobs\EmailEvents\EmailOpenedJob;
use App\Jobs\EmailEvents\EmailReOpenedJob;
use App\Jobs\EmailEvents\MassiveEmailSentJob;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Jobs\EmailEvents\SendCreatedUserEmailJob;
use App\Jobs\EmailEvents\SendEnabledUserEmailJob;
use App\Jobs\EmailEvents\SendDisabledUserEmailJob;
use App\Jobs\EmailEvents\MassiveEmailSentOrScheduledJob;
use App\Jobs\EmailEvents\MarkLeadContactEmailsAsBouncedJob;
use App\Jobs\EmailEvents\MarkLeadContactEmailsAsComplainedJob;
use App\Jobs\EmailEvents\MarkLeadContactEmailsAsUnsubscribedJob;
use App\Jobs\AutomationEvents\SendDeletedAutomationEmailAlertJob;
use App\Jobs\AutomationEvents\SendDisabledAutomationEmailAlertJob;


class EmailEventsDispatcherService
{

    use CustomDispatch;

    private $eventQueueName;
    private $queueConnection;


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    // Este job solamente ejecuta AutomationProposalModifyLeadAfterSend SI APLICA.
    public function dispatchEmailSentJob(Email $email)
    {
        $this->doCustomDispatch(EmailSentJob::class, [$email->id], null, $email->client_id);
    }


    public function dispatchEmailOpenedJob(Email $openedEmail)
    {
        $this->doCustomDispatch(EmailOpenedJob::class, [$openedEmail->id], null, $openedEmail->client_id);
    }


    public function dispatchEmailReOpenedJob(Email $openedEmail)
    {
        $this->doCustomDispatch(EmailReOpenedJob::class, [$openedEmail->id], null, $openedEmail->client_id);
    }


    public function dispatchMarkLeadContactEmailsAsBouncedJob(Collection $leadContactEmails)
    {
        $client = $leadContactEmails->first()->client;
        $params = [$client->id, $leadContactEmails->pluck('id')->toArray()];
        $this->doCustomDispatch(MarkLeadContactEmailsAsBouncedJob::class, $params, null, $client->id);
    }


    public function dispatchMarkLeadContactEmailsAsComplainedJob(Collection $leadContactEmails)
    {
        $client = $leadContactEmails->first()->client;
        $params = [$client->id, $leadContactEmails->pluck('id')->toArray()];
        $this->doCustomDispatch(MarkLeadContactEmailsAsComplainedJob::class, $params, null, $client->id);
    }


    public function dispatchMarkLeadContactEmailsAsUnsubscribedJob(Collection $leadContactEmails)
    {
        $client = $leadContactEmails->first()->client;
        $params = [$client->id, $leadContactEmails->pluck('id')->toArray()];
        $this->doCustomDispatch(MarkLeadContactEmailsAsUnsubscribedJob::class, $params, null, $client->id);
    }


    public function dispatchMassiveEmailSentOrScheduledJob(User $user, array $emailsExternalIds, string $type)
    {
        $params = [$user->id, $emailsExternalIds, $type];
        $this->doCustomDispatch(MassiveEmailSentOrScheduledJob::class, $params, null, $user->client_id);
    }


    public function dispatchSendCreatedUserEmailJob(User $createdUser, User $loginUser)
    {
        $params = [$createdUser->id, $loginUser->id];
        $this->doCustomDispatch(SendCreatedUserEmailJob::class, $params, null, $loginUser->client_id);
    }


    public function dispatchSendEnabledUserEmailJob(User $enabledUser, User $loginUser)
    {
        $params = [$enabledUser->id, $loginUser->id];
        $this->doCustomDispatch(SendEnabledUserEmailJob::class, $params, null, $loginUser->client_id);
    }


    public function dispatchSendDisabledUserEmailJob(User $disabledUser, User $loginUser)
    {
        $params = [$disabledUser->id, $loginUser->id];
        $this->doCustomDispatch(SendDisabledUserEmailJob::class, $params, null, $loginUser->client_id);
    }


    public function dispatchSendDisabledAutomationEmailAlertJob(
        AutomationTask |
        AutomationProposal |
        AutomationEmailSend |
        WAutomationProposal |
        WAutomationSequence |
        WAutomationAfterSend $disabledAutomation,
        User $loginUser
    ) {
        $params = [$disabledAutomation, $loginUser->id];
        $this->doCustomDispatch(SendDisabledAutomationEmailAlertJob::class, $params, null, $loginUser->client_id);
    }


    public function dispatchSendDeletedAutomationEmailAlertJob(
        AutomationTask |
        AutomationNewLead |
        AutomationEmailSend |
        WAutomationSequence $deletedAutomation,
        User $loginUser
    ) {
        $params = [$deletedAutomation, $loginUser->id];
        $this->doCustomDispatch(SendDeletedAutomationEmailAlertJob::class, $params, null, $loginUser->client_id);
    }

}
