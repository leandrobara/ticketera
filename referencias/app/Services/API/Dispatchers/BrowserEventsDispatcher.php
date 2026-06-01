<?php

namespace App\Services\API\Dispatchers;

use Exception;
use App\Models\User;
use App\Models\Lead;
use App\Models\Task;
use App\Models\Email;
use App\Events\NewLeadEvent;
use App\Events\NewTaskEvent;
use App\Models\EmailTemplate;
use App\Events\ExpiringTaskEvent;
use App\Events\NewManualLeadEvent;
use Illuminate\Support\Collection;
use App\Events\TaskUserChangeEvent;
use App\Events\OpenedProposalEvent;
use App\Events\ReOpenedProposalEvent;
use App\Events\NewEmailTemplateEvent;
use App\Events\LeadTagsModifiedEvent;
use App\Events\LeadStatusModifiedEvent;
use App\Services\Traits\CustomBroadcast;
use App\Events\TaskMassiveUserChangeEvent;


class BrowserEventsDispatcher
{

    use CustomBroadcast;


    public function notifyNewLead(Lead $lead): bool
    {
        $this->doCustomBroadcast(new NewLeadEvent($lead->id));
        return true;
    }


    public function notifyNewTask(Task $task, User $assignerUser): bool
    {
        $this->doCustomBroadcast(new NewTaskEvent($task->id, $assignerUser->id));
        return true;
    }


    public function notifyTaskUserChange(Task $task, User $assignerUser): bool
    {
        $this->doCustomBroadcast(new TaskUserChangeEvent($task->id, $assignerUser->id));
        return true;
    }


    public function notifyMassiveTaskUserChange(Collection $taskIds, int $assignerUserId, int $newUserId): bool
    {
        $this->doCustomBroadcast(new TaskMassiveUserChangeEvent($taskIds->toArray(), $assignerUserId, $newUserId));
        return true;
    }


    public function notifyNewEmailTemplate(EmailTemplate $emailTempate): bool
    {
        $this->doCustomBroadcast(new NewEmailTemplateEvent($emailTempate->id));
        return true;
    }


    public function notifyNewManualLead(Lead $lead): bool
    {
        $this->doCustomBroadcast(new NewManualLeadEvent($lead->id));
        return true;
    }


    public function notifyOpenedProposal(Email $email): bool
    {
        $this->doCustomBroadcast(new OpenedProposalEvent($email->id));
        return true;
    }


    public function notifyReOpenedProposal(Email $email): bool
    {
        $event = new ReOpenedProposalEvent($email->id);
        $this->doCustomBroadcast($event);
        return true;
    }


    public function notifyExpiringTask(Task $task): bool
    {
        $this->doCustomBroadcast(new ExpiringTaskEvent($task->id));
        return true;
    }


    public function notifyLeadTagsModified(Lead $lead): bool
    {
        $this->doCustomBroadcast(new LeadTagsModifiedEvent($lead->id));
        return true;
    }


    public function notifyLeadStatusModified(Lead $lead): bool
    {
        $this->doCustomBroadcast(new LeadStatusModifiedEvent($lead->id));
        return true;
    }

}
