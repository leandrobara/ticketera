<?php

namespace App\Services\API\Dispatchers;

use DateTime;
use Exception;
use App\Models\User;
use App\Models\Lead;
use App\Models\Status;
use App\Models\LeadContactEmail;
use Illuminate\Support\Collection;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Database\Eloquent\Model;
use App\Services\Traits\CustomDispatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\LeadEvents\SendNewLeadEmailJob;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Jobs\LeadEvents\ValidateLeadEmailsJob;
use App\Jobs\LeadEvents\ApplyNewLeadAutomationJob;
use App\Jobs\LeadEvents\MultipleLeadUserChangeJob;
use App\Jobs\LeadEvents\SaveLeadTagLastUsedDateJob;
use App\Jobs\LeadEvents\SendLeadUserChangeEmailJob;
use App\Jobs\LeadEvents\ValidateLeadContactEmailJob;
use App\Jobs\LeadEvents\LeadStatusMassiveChangedJob;
use App\Overrides\Dispatchers\CustomPendingDispatch;
use App\Jobs\LeadEvents\SendNewLeadWhatsAppMessageJob;
use App\Jobs\LeadEvents\LeadDuplicatedEmailManagementJob;
use App\Jobs\LeadEvents\LeadDuplicatedPhoneManagementJob;


class LeadEventsDispatcherService
{

    use CustomDispatch;
    
    private $eventQueueName;
    private $queueConnection;


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function dispatchLeadStatusMassiveChangedJob(int $leadId, int $statusId, int $clientId, int $userId)
    {
        $this->doCustomDispatch(
            LeadStatusMassiveChangedJob::class, [$leadId, $statusId, $userId, $clientId], null, $clientId
        );
    }


    // Delay some seconds, so dispatchApplyNewLeadAutomationJob can run first
    public function dispatchSendNewLeadEmailJob(Lead $lead, ?int $delaySecs = 15)
    {
        $this->doCustomDispatch(SendNewLeadEmailJob::class, [$lead->id], $delaySecs, $lead->client_id);
    }


    public function dispatchLeadUserChangeEmailJob(Lead $lead, int $oldUserId)
    {
        // Delay some seconds, so dispatchApplyNewLeadAutomationJob can run first
        $this->doCustomDispatch(SendLeadUserChangeEmailJob::class, [$lead->id, $oldUserId], 15, $lead->client_id);
    }


    // Delay some seconds, so dispatchApplyNewLeadAutomationJob can run first
    public function dispatchSendNewLeadWhatsAppMessageJob(Lead $lead, ?int $delaySecs = 15)
    {
        $this->doCustomDispatch(SendNewLeadWhatsAppMessageJob::class, [$lead->id], $delaySecs, $lead->client_id);
    }


    public function dispatchMultipleLeadUserChangeJob(array $leadIds, User $user, User $loginUser)
    {
        $this->doCustomDispatch(
            MultipleLeadUserChangeJob::class, [$leadIds, $user->id, $loginUser->id], null, $user->client_id
        );
    }


    public function dispatchApplyNewLeadAutomationJob(Lead $lead, ?int $delaySecs = null)
    {
        // $lead = $this->refreshModelIfSyncedQueue($lead); // if the queue is in sync refresh lead
        $this->doCustomDispatch(ApplyNewLeadAutomationJob::class, [$lead->id], $delaySecs, $lead->client_id);
    }


    public function dispatchLeadDuplicatedEmailManagementJob(
        int $clientId,
        string $action,
        string $newEmailAddr,
        ?string $previousEmailAddr = null,
        ?int $delaySecs = null
    ) {
        $this->doCustomDispatch(
            LeadDuplicatedEmailManagementJob::class,
            [$clientId, $action, $newEmailAddr, $previousEmailAddr],
            $delaySecs,
            $clientId,
            config('queue.lead_duplicated_email_management_queue')
        );
    }


    public function dispatchLeadDuplicatedPhoneManagementJob(
        int $clientId,
        string $action,
        string $newPhoneNumber,
        ?string $previousPhoneNumber = null,
        ?int $delaySecs = null
    ) {
        $this->doCustomDispatch(
            LeadDuplicatedPhoneManagementJob::class,
            [$clientId, $action, $newPhoneNumber, $previousPhoneNumber],
            $delaySecs,
            $clientId,
            config('queue.lead_duplicated_phone_management_queue')
        );
    }


    public function dispatchSaveLeadTagLastUsedDateJob(
        int $clientId,
        Collection $prevLeadTagIds,
        Collection $leadTagsAfterChange,
        string $assignType = 'add'
    ) {
        $dateNow = new DateTime('now');
        $leadTagIdsAfterChange = $leadTagsAfterChange->pluck('id');

        if ($assignType == 'add') {
            $existentTagIds = $prevLeadTagIds->intersect($leadTagIdsAfterChange);
            $addedTagIds = $leadTagIdsAfterChange->diff($existentTagIds)->values();
            foreach ($addedTagIds as $addedTagId) {
                $params = [$addedTagId, $dateNow];
                $this->doCustomDispatch(SaveLeadTagLastUsedDateJob::class, $params, null, $clientId);
            }
        }
        if ($assignType == 'replace') {
            $existentTagIds = $prevLeadTagIds->intersect($leadTagIdsAfterChange);
            $addedTagIds = $leadTagIdsAfterChange->diff($existentTagIds)->values();
            foreach ($addedTagIds as $addedTagId) {
                $params = [$addedTagId, $dateNow];
                $this->doCustomDispatch(SaveLeadTagLastUsedDateJob::class, $params, null, $clientId);
            }
        }
    }


    protected function refreshModelIfSyncedQueue(Model $model): Model
    {
        if (config('queue.default') === 'sync') {
            $model->refresh();
        }
        return $model;
    }

}
