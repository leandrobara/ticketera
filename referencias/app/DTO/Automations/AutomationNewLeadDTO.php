<?php

namespace App\DTO\Automations;

use Illuminate\Support\Collection;

class AutomationNewLeadDTO
{

    public $client;
    public $addTags;
    public $addNewTask;
    public $addNewNote;
    public $formFields;
    public $newNoteText;
    public $newTaskTitle;
    public $assignUserIds;
    public $assignQuality;
    public $utmParameters;
    public $doNotSendEmail;
    public $statusToAssign;
    public $groupedEmailBody;
    public $sendGroupedEmail;
    public $applicationOrder;
    public $trackingParameters;
    public $triggeringLandings;
    public $triggeringLeadType;
    public $newTaskDescription;
    public $newTaskDaysToExpire;
    public $groupedEmailSubject;
    public $autoReplySendMinHour;
    public $autoReplySendMaxHour;
    public $autoReplyEmailTemplate;
    public $acquisitionChannelToAdd;
    public $triggerIfPhoneRepeatead;
    public $triggerIfEmailRepeatead;
    public $leadCustomFieldsMapping;
    public $leadCustomFieldsMatch;
    public $doNotSendWhatsAppMessage;
    public $sendGroupedWhatsAppMessage;
    public $groupedWhatsAppMessageText;
    public $autoReplyDoNotSendOutOfHour;
    public $autoReplyAskPhoneEmailTemplate;


    public static function build($data)
    {
        $dto = new AutomationNewLeadDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->client = $data['client'] ?? null;
        $this->assignUserIds = $data['assign_user_ids'] ?? [];
        $this->statusToAssign = $data['statusToAssign'] ?? null;
        $this->triggeringLandings = $data['triggeringLandings'] ?? new Collection();
        $this->autoReplyEmailTemplate = $data['autoReplyEmailTemplate'] ?? null;
        $this->triggerIfEmailRepeatead = $data['trigger_if_email_repeatead'] ?? false;
        $this->triggerIfPhoneRepeatead = $data['trigger_if_phone_repeatead'] ?? false;
        $this->autoReplyAskPhoneEmailTemplate = $data['autoReplyAskPhoneEmailTemplate'] ?? null;
        $this->addTags = $data['addTags'] ?? new Collection();
        $this->triggeringLeadType = $data['triggering_lead_type'];
        $this->assignQuality = $data['assign_quality'] ?? null;
        $this->doNotSendEmail = $data['do_not_send_email'] ?? false;
        $this->doNotSendWhatsAppMessage = $data['do_not_send_whatsapp_message'] ?? false;
        $this->sendGroupedEmail = $data['send_grouped_email'] ?? false;
        $this->sendGroupedWhatsAppMessage = $data['send_grouped_whatsapp_message'] ?? false;
        $this->groupedWhatsAppMessageText = $data['grouped_whatsapp_message_text'] ?? null;
        $this->groupedEmailSubject = $data['grouped_email_subject'];
        $this->groupedEmailBody = $data['grouped_email_body'] ?? null;
        $this->autoReplySendMinHour = $data['auto_reply_send_min_hour'] ?? null;
        $this->autoReplySendMaxHour = $data['auto_reply_send_max_hour'] ?? null;
        $this->acquisitionChannelToAdd = $data['acquisitionChannelToAdd'] ?? null;
        $this->autoReplyDoNotSendOutOfHour = $data['auto_reply_do_not_send_out_of_hour'] ?? false;
        $this->addNewTask = $data['add_new_task'] ?? false;
        $this->newTaskTitle = $data['new_task_title'];
        $this->newTaskDescription = $data['new_task_description'] ?? null;
        $this->newTaskDaysToExpire = $data['new_task_days_to_expire'] ?? null;
        $this->addNewNote = $data['add_new_note'] ?? null;
        $this->newNoteText = $data['new_note_text'] ?? null;
        $this->formFields = $data['form_fields'] ?? [];
        $this->utmParameters = $data['utm_parameters'] ?? [];
        $this->trackingParameters = $data['tracking_parameters'] ?? [];
        $this->leadCustomFieldsMapping = $data['lead_custom_fields_mapping'] ?? [];
        $this->leadCustomFieldsMatch = $data['lead_custom_fields_match'] ?? [];
        $this->applicationOrder = $data['application_order'] ?? 1;
    }
}
