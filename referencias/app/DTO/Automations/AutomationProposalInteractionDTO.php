<?php

namespace App\DTO\Automations;

use Illuminate\Support\Collection;


class AutomationProposalInteractionDTO
{

    public $client;
    public $addTags;
    public $removeTags;
    public $addNewTask;
    public $triggerType;
    public $assignStatus;
    public $newTaskTitle;
    public $cancellingTags;
    public $cancellingStatus;
    public $automationProposal;
    public $newTaskDescription;
    public $newTaskIsImportant;
    public $newTaskDaysToExpire;
    public $addOpenedProposalTag;
    public $sendNotificationEmailToUser;
    public $notifyOnlyIfLeadQUalityIsGt;


    public static function build($data)
    {
        $dto = new AutomationProposalInteractionDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->client = $data['client'] ?? null;
        $this->addNewTask = $data['add_new_task'] ?? false;
        $this->assignStatus = $data['assignStatus'] ?? null;
        $this->triggerType = $data['trigger_type'] ?? 'open';
        $this->newTaskTitle = $data['new_task_title'] ?? null;
        $this->addTags = $data['addTags'] ?? new Collection();
        $this->removeTags = $data['removeTags'] ?? new Collection();
        $this->automationProposal = $data['automationProposal'] ?? null;
        $this->newTaskDescription = $data['new_task_description'] ?? null;
        $this->cancellingTags = $data['cancellingTags'] ?? new Collection();
        $this->newTaskIsImportant = $data['new_task_is_important'] ?? false;
        $this->newTaskDaysToExpire = $data['new_task_days_to_expire'] ?? null;
        $this->cancellingStatus = $data['cancellingStatus'] ?? new Collection();
        $this->addOpenedProposalTag = $data['add_opened_proposal_tag'] ?? false;
        $this->sendNotificationEmailToUser = $data['send_notification_email_to_user'];
        $this->notifyOnlyIfLeadQUalityIsGt = $data['notify_only_if_lead_quality_is_gt'];
    }

}
