<?php

namespace App\Repositories\Automations;

use Exception;
use App\Models\Client;
use App\Models\AutomationNewLead;
use Illuminate\Support\Collection;
use App\Exceptions\DatabaseException;
use App\DTO\Automations\AutomationNewLeadDTO;
use App\DTO\Automations\Parameters\ListAutomationNewLeadDTO;


class AutomationNewLeadRepository
{

    public function list(ListAutomationNewLeadDTO $dto): Collection
    {
        // @TODO: Aplicar misma lógica que Views/LeadService::list
        $automations = AutomationNewLead::where('client_id', $dto->client->id)
            ->orderBy('application_order', 'asc')
            ->get()
        ;
        return $automations;
    }


    public function findAutomationsByClient(Client $client): Collection
    {
        $automations = $this->findAutomationsByClientId($client->id);
        return $automations;
    }


    public function findAutomationsByClientId(int $clientId): Collection
    {
        $automations = AutomationNewLead::where('client_id', $clientId)->orderBy('application_order', 'asc')->get();
        return $automations;
    }


    public function create(AutomationNewLeadDTO $dto): AutomationNewLead
    {
        $assignUserIds = [];
        if (count($dto->assignUserIds)) {
            $assignUserIds = $dto->assignUserIds;
        }

        $autoReplyEmailTemplateId = null;
        if ($dto->autoReplyEmailTemplate) {
            $autoReplyEmailTemplateId = $dto->autoReplyEmailTemplate->id;
        }

        $autoReplyAskPhoneEmailTemplateId = null;
        if ($dto->autoReplyAskPhoneEmailTemplate) {
            $autoReplyAskPhoneEmailTemplateId = $dto->autoReplyAskPhoneEmailTemplate->id;
        }

        $triggeringLandingsIds = null;
        if ($dto->triggeringLandings) {
            $triggeringLandingsIds = $dto->triggeringLandings->pluck('id');
        }

        $addTagsIds = null;
        if ($dto->addTags) {
            $addTagsIds = $dto->addTags->pluck('id');
        }

        $addAcquisitionChannelId = null;
        if ($dto->acquisitionChannelToAdd) {
            $addAcquisitionChannelId = $dto->acquisitionChannelToAdd->id;
        }

        $statusIdToAssign = null;
        if ($dto->statusToAssign) {
            $statusIdToAssign = $dto->statusToAssign->id;
        }

        $data = [
            'add_tags_ids' => $addTagsIds,
            'client_id' => $dto->client->id,
            'add_new_note' => $dto->addNewNote,
            'add_new_task' => $dto->addNewTask,
            'assign_user_ids' => $assignUserIds,
            'new_note_text' => $dto->newNoteText,
            'new_task_title' => $dto->newTaskTitle,
            'assign_quality' => $dto->assignQuality,
            'do_not_send_email'  => $dto->doNotSendEmail,
            'application_order' => $dto->applicationOrder,
            'send_grouped_email' => $dto->sendGroupedEmail,
            'grouped_email_body' => $dto->groupedEmailBody,
            'status_id_to_assign' => $dto->statusToAssign?->id,
            'triggering_landing_ids' => $triggeringLandingsIds,
            'triggering_lead_type' => $dto->triggeringLeadType,
            'new_task_description' => $dto->newTaskDescription,
            'grouped_email_subject' => $dto->groupedEmailSubject,
            'new_task_days_to_expire' => $dto->newTaskDaysToExpire,
            'add_acquisition_channel_id' => $addAcquisitionChannelId,
            'auto_reply_send_min_hour' => $dto->autoReplySendMinHour,
            'auto_reply_send_max_hour' => $dto->autoReplySendMaxHour,
            'auto_reply_email_template_id' => $autoReplyEmailTemplateId,
            'trigger_if_email_repeatead' => $dto->triggerIfEmailRepeatead,
            'trigger_if_phone_repeatead' => $dto->triggerIfPhoneRepeatead,
            'do_not_send_whatsapp_message' => $dto->doNotSendWhatsAppMessage,
            'send_grouped_whatsapp_message' => $dto->sendGroupedWhatsAppMessage,
            'grouped_whatsapp_message_text' => $dto->groupedWhatsAppMessageText,
            'auto_reply_do_not_send_out_of_hour' => $dto->autoReplyDoNotSendOutOfHour,
            'auto_reply_ask_phone_email_template_id' => $autoReplyAskPhoneEmailTemplateId,
        ];

        $automationNewLead = new AutomationNewLead($data);
        $automationNewLead->saveOrFail();
        
        return $automationNewLead->fresh();
    }


    public function update(AutomationNewLead $automation, AutomationNewLeadDTO $dto): AutomationNewLead
    {
        $assignUserIds = [];
        if (count($dto->assignUserIds)) {
            $assignUserIds = $dto->assignUserIds;
        }

        $autoReplyEmailTemplateId = null;
        if ($dto->autoReplyEmailTemplate) {
            $autoReplyEmailTemplateId = $dto->autoReplyEmailTemplate->id;
        }

        $autoReplyAskPhoneEmailTemplateId = null;
        if ($dto->autoReplyAskPhoneEmailTemplate) {
            $autoReplyAskPhoneEmailTemplateId = $dto->autoReplyAskPhoneEmailTemplate->id;
        }

        $triggeringLandingsIds = null;
        if ($dto->triggeringLandings) {
            $triggeringLandingsIds = $dto->triggeringLandings->pluck('id');
        }

        $addTagsIds = null;
        if ($dto->addTags) {
            $addTagsIds = $dto->addTags->pluck('id');
        }

        $addAcquisitionChannelId = null;
        if ($dto->acquisitionChannelToAdd) {
            $addAcquisitionChannelId = $dto->acquisitionChannelToAdd->id;
        }

        $statusIdToAssign = null;
        if ($dto->statusToAssign) {
            $statusIdToAssign = $dto->statusToAssign->id;
        }

        $data = [
            'add_tags_ids' => $addTagsIds,
            'client_id' => $dto->client->id,
            'add_new_note' => $dto->addNewNote,
            'add_new_task' => $dto->addNewTask,
            'assign_user_ids' => $assignUserIds,
            'new_note_text' => $dto->newNoteText,
            'new_task_title' => $dto->newTaskTitle,
            'assign_quality' => $dto->assignQuality,
            'status_id_to_assign' => $statusIdToAssign,
            'do_not_send_email'  => $dto->doNotSendEmail,
            'application_order' => $dto->applicationOrder,
            'send_grouped_email' => $dto->sendGroupedEmail,
            'grouped_email_body' => $dto->groupedEmailBody,
            'triggering_landing_ids' => $triggeringLandingsIds,
            'triggering_lead_type' => $dto->triggeringLeadType,
            'new_task_description' => $dto->newTaskDescription,
            'grouped_email_subject' => $dto->groupedEmailSubject,
            'new_task_days_to_expire' => $dto->newTaskDaysToExpire,
            'add_acquisition_channel_id' => $addAcquisitionChannelId,
            'auto_reply_send_min_hour' => $dto->autoReplySendMinHour,
            'auto_reply_send_max_hour' => $dto->autoReplySendMaxHour,
            'auto_reply_email_template_id' => $autoReplyEmailTemplateId,
            'trigger_if_email_repeatead' => $dto->triggerIfEmailRepeatead,
            'trigger_if_phone_repeatead' => $dto->triggerIfPhoneRepeatead,
            'do_not_send_whatsapp_message' => $dto->doNotSendWhatsAppMessage,
            'send_grouped_whatsapp_message' => $dto->sendGroupedWhatsAppMessage,
            'grouped_whatsapp_message_text' => $dto->groupedWhatsAppMessageText,
            'auto_reply_do_not_send_out_of_hour' => $dto->autoReplyDoNotSendOutOfHour,
            'auto_reply_ask_phone_email_template_id' => $autoReplyAskPhoneEmailTemplateId,
        ];
        $automation->fill($data);
        $automation->saveOrFail();
        
        return $automation->fresh();
    }


    public function delete(AutomationNewLead $automation): AutomationNewLead
    {
        $automation->delete();
        return $automation->fresh();
    }

}
