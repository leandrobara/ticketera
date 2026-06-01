<?php

namespace App\Services\API\Automations;

use DateTime;
use Exception;
use Throwable;
use DateTimeZone;
use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Models\AutomationLog;
use App\Models\EmailTemplate;
use App\Models\AutomationNewLead;
use App\Services\API\UserService;
use App\Services\API\LeadService;
use App\Services\API\NoteService;
use App\Services\API\TaskService;
use Illuminate\Support\Collection;
use App\Services\API\EmailService;
use Illuminate\Support\Facades\DB;
use App\Helpers\MermaidChartHelper;
use App\DTO\EmailScheduleParametersDTO;
use App\Services\Traits\GetUserFromRequest;
use App\Services\API\LeadCustomFieldService;
use App\Services\API\LeadContactEmailService;
use App\Services\API\LeadContactPhoneService;
use App\DTO\Automations\AutomationNewLeadDTO;
use App\Services\Traits\GetClientFromRequest;
use App\Services\Traits\StoresExistentInstance;
use App\Services\API\LeadNotificationEmailService;
use App\Services\API\Notifications\NotificationService;
use App\Services\API\LeadNotificationWhatsAppMessageService;
use App\DTO\Automations\Parameters\ListAutomationNewLeadDTO;
use App\Repositories\Automations\AutomationNewLeadRepository;
use App\Services\API\Dispatchers\LeadEventsDispatcherService;
use App\Services\API\Dispatchers\EmailEventsDispatcherService;
use App\Services\API\Actions\LeadService as ActionsLeadService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\Services\API\Automations\AutomationNewLeadCustomFieldMatchService;
use App\Exceptions\Services\Automations\AutomationNewLeadServiceException;
use App\Services\API\Automations\AutomationNewLeadTrackingParameterService;
use App\Services\API\Automations\AutomationNewLeadCustomFieldMappingService;
use App\Exceptions\Services\EmailService\EmailSendValidationUserNotEnabledException;


class AutomationNewLeadService
{

    use GetClientFromRequest, GetUserFromRequest, StoresExistentInstance;

    protected $leadService;
    protected $actionsLeadService;


    // Required setters (not in constructor to avoid circular injection)
    public function setLeadService(LeadService $leadService): AutomationNewLeadService
    {
        $this->leadService = $leadService;
        return $this;
    }


    public function setActionsLeadService(ActionsLeadService $actionsLeadService): AutomationNewLeadService
    {
        $this->actionsLeadService = $actionsLeadService;
        return $this;
    }


    public function __construct(
        private UserService $userService,
        private TaskService $taskService,
        private NoteService $noteService,
        private EmailService $emailService,
        private MermaidChartHelper $mermaidChartHelper,
        private NotificationService $notificationService,
        private AutomationLogService $automationLogService,
        private LeadCustomFieldService $leadCustomFieldService,
        private LeadContactEmailService $leadContactEmailService,
        private LeadContactPhoneService $leadContactPhoneService,
        private AutomationNewLeadRepository $automationNewLeadRepository,
        private LeadNotificationEmailService $leadNotificationEmailService,
        private EmailEventsDispatcherService $emailEventsDispatcherService,
        private AutomationNewLeadFormFieldService $automationNewLeadFormFieldService,
        private AutomationNewLeadUtmParameterService $automationNewLeadUtmParameterService,
        private LeadNotificationWhatsAppMessageService $leadNotificationWhatsAppMessageService,
        private AutomationNewLeadCustomFieldMatchService $automationNewLeadCustomFieldMatchService,
        private AutomationNewLeadTrackingParameterService $automationNewLeadTrackingParameterService,
        private AutomationNewLeadCustomFieldMappingService $automationNewLeadCustomFieldMappingService,
    ) {
        $this->setExistentInstance($this);
    }


    public function list(ListAutomationNewLeadDTO $paramsDTO): Collection
    {
        $paramsDTO->client = $this->getClient(); //Override this just in case
        $automations = $this->automationNewLeadRepository->list($paramsDTO);
        return $automations;
    }


    public function create(AutomationNewLeadDTO $dto): AutomationNewLead
    {
        $dto->client = $this->getClient();
        $automationNewLead = $this->automationNewLeadRepository->create($dto);
        if ($dto->formFields) {
            $this->automationNewLeadFormFieldService->create($automationNewLead, $dto);
        }
        if ($dto->utmParameters) {
            $this->automationNewLeadUtmParameterService->create($automationNewLead, $dto);
        }
        if ($dto->leadCustomFieldsMapping) {
            $this->automationNewLeadCustomFieldMappingService->create($automationNewLead, $dto);
        }
        if ($dto->leadCustomFieldsMatch) {
            $this->automationNewLeadCustomFieldMatchService->create($automationNewLead, $dto);
        }
        if ($dto->trackingParameters) {
            $this->automationNewLeadTrackingParameterService->create($automationNewLead, $dto);
        }
        return $automationNewLead;
    }


    public function update(AutomationNewLead $automation, AutomationNewLeadDTO $dto): AutomationNewLead
    {
        if (!$this->parametersChanged($automation, $dto)) {
            return $automation;
        }

        $ruleWasApplied = $this->automationLogService->findByAutomationNewLead($automation)->isNotEmpty();
        // If rule was never applied, I can update the row.
        if (!$ruleWasApplied) {
            try {
                DB::beginTransaction();
                $automation = $this->automationNewLeadRepository->update($automation, $dto);
                if ($this->automationFormFieldsChanged($automation, $dto)) {
                    $this->automationNewLeadFormFieldService->deleteAllAndCreate($automation, $dto);
                    $automation->load('formFieldsToMatch');
                }
                if ($this->automationUtmParametersChanged($automation, $dto)) {
                    $this->automationNewLeadUtmParameterService->deleteAllAndCreate($automation, $dto);
                    $automation->load('utmParametersToMatch');
                }
                if ($this->leadCustomFieldsMappingChanged($automation, $dto)) {
                    $this->automationNewLeadCustomFieldMappingService->deleteAllAndCreate($automation, $dto);
                    $automation->load('leadCustomFieldsMapping');
                }
                if ($this->leadCustomFieldsMatchChanged($automation, $dto)) {
                    $this->automationNewLeadCustomFieldMatchService->deleteAllAndCreate($automation, $dto);
                    $automation->load('leadCustomFieldsMatch');
                }
                if ($this->trackingParametersChanged($automation, $dto)) {
                    $this->automationNewLeadTrackingParameterService->deleteAllAndCreate($automation, $dto);
                    $automation->load('trackingParametersToMatch');
                }
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            return $automation;
        }

        try {
            DB::beginTransaction();
            // If rule was applied at least once, I soft-delete it and create a new one.
            $this->automationNewLeadRepository->delete($automation);
            $automation = $this->automationNewLeadRepository->create($dto);
            if ($this->automationFormFieldsChanged($automation, $dto)) {
                $this->automationNewLeadFormFieldService->deleteAllAndCreate($automation, $dto);
                $automation->load('formFieldsToMatch');
            }
            if ($this->automationUtmParametersChanged($automation, $dto)) {
                $this->automationNewLeadUtmParameterService->deleteAllAndCreate($automation, $dto);
                $automation->load('utmParametersToMatch');
            }
            if ($this->leadCustomFieldsMappingChanged($automation, $dto)) {
                $this->automationNewLeadCustomFieldMappingService->deleteAllAndCreate($automation, $dto);
                $automation->load('leadCustomFieldsMapping');
            }
            if ($this->leadCustomFieldsMatchChanged($automation, $dto)) {
                $this->automationNewLeadCustomFieldMatchService->deleteAllAndCreate($automation, $dto);
                $automation->load('leadCustomFieldsMatch');
            }
            if ($this->trackingParametersChanged($automation, $dto)) {
                $this->automationNewLeadTrackingParameterService->deleteAllAndCreate($automation, $dto);
                $automation->load('trackingParametersToMatch');
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $automation;
    }


    public function delete(AutomationNewLead $automationNewLead): AutomationNewLead
    {
        try {
            // delete automation new lead chat keywords and form fields
            DB::beginTransaction();
            $this->automationNewLeadFormFieldService->deleteAllByAutomation($automationNewLead);
            $this->automationNewLeadUtmParameterService->deleteAllByAutomation($automationNewLead);
            $this->automationNewLeadCustomFieldMappingService->deleteAllByAutomation($automationNewLead);
            $this->automationNewLeadCustomFieldMatchService->deleteAllByAutomation($automationNewLead);
            $this->automationNewLeadTrackingParameterService->deleteAllByAutomation($automationNewLead);
            $deletedAutomation = $this->automationNewLeadRepository->delete($automationNewLead);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->emailEventsDispatcherService->dispatchSendDeletedAutomationEmailAlertJob(
            $deletedAutomation, $this->getUser()
        );
        return $deletedAutomation;
    }


    public function parametersChanged(AutomationNewLead $automation, AutomationNewLeadDTO $dto)
    {
        $statusIdToAssign = $dto->statusToAssign?->id ?? null;
        $addTags = $automation->tagsToAdd->pluck('id')->toArray();
        $newAcquisitionChannelToAddId = $dto->acquisitionChannelToAdd?->id ?? null;
        $triggeringLandings = $automation->triggeringLandings->pluck('id')->toArray();

        $assignUserIds = $dto->assignUserIds ?? [];
        $autoReplyAskPhoneEmailTemplateId = $dto->autoReplyAskPhoneEmailTemplate
            ? $dto->autoReplyAskPhoneEmailTemplate->id
            : null
        ;
        $dtoAutoReplyEmailTemplateId = $dto->autoReplyEmailTemplate ? $dto->autoReplyEmailTemplate->id : null;
        if (
            $triggeringLandings === $dto->triggeringLandings->pluck('id')->toArray() &&
            $addTags === $dto->addTags->pluck('id')->toArray() &&
            $automation->triggering_lead_type === $dto->triggeringLeadType &&
            $automation->trigger_if_email_repeatead === $dto->triggerIfEmailRepeatead &&
            $automation->trigger_if_phone_repeatead === $dto->triggerIfPhoneRepeatead &&
            $automation->assign_user_ids === $assignUserIds &&
            $automation->assign_quality === $dto->assignQuality &&
            $automation->do_not_send_email === $dto->doNotSendEmail &&
            $automation->do_not_send_whatsapp_message === $dto->doNotSendWhatsAppMessage &&
            $automation->send_grouped_email === $dto->sendGroupedEmail &&
            $automation->send_grouped_whatsapp_message === $dto->sendGroupedWhatsAppMessage &&
            $automation->grouped_whatsapp_message_text === $dto->groupedWhatsAppMessageText &&
            $automation->grouped_email_subject === $dto->groupedEmailSubject &&
            $automation->grouped_email_body === $dto->groupedEmailBody &&
            $automation->auto_reply_ask_phone_email_template_id === $autoReplyAskPhoneEmailTemplateId &&
            $automation->auto_reply_email_template_id === $dtoAutoReplyEmailTemplateId &&
            $automation->auto_reply_send_min_hour === $dto->autoReplySendMinHour &&
            $automation->auto_reply_send_max_hour === $dto->autoReplySendMaxHour &&
            $automation->auto_reply_do_not_send_out_of_hour === $dto->autoReplyDoNotSendOutOfHour &&
            $automation->add_new_task === $dto->addNewTask &&
            $automation->new_task_title === $dto->newTaskTitle &&
            $automation->new_task_description === $dto->newTaskDescription &&
            $automation->new_task_days_to_expire === $dto->newTaskDaysToExpire &&
            $automation->add_new_note === $dto->addNewNote &&
            $automation->new_note_text === $dto->newNoteText &&
            $automation->status_id_to_assign === $statusIdToAssign &&
            $automation->add_acquisition_channel_id === $newAcquisitionChannelToAddId &&
            $automation->application_order === $dto->applicationOrder &&
            (!$this->automationFormFieldsChanged($automation, $dto)) &&
            (!$this->automationUtmParametersChanged($automation, $dto)) &&
            (!$this->leadCustomFieldsMappingChanged($automation, $dto)) &&
            (!$this->leadCustomFieldsMatchChanged($automation, $dto)) &&
            (!$this->trackingParametersChanged($automation, $dto))
        ) {
            return false;
        }
        return true;
    }


    public function apply(Lead $lead): Collection
    {
        $appliedAutomations = new Collection();
        $automations = $this->findAutomationsByClientId($lead->client_id);

        foreach ($automations as $automation) {
            $existent = $this->automationLogService->findOneByLeadAndAutomationNewLead($lead, $automation);
            if ($existent) {
                continue;
            }

            $lead = $lead->fresh();

            $matches = $this->leadMatchesAutomation($lead, $automation);
            if ($matches) {
                // Fix: me aseguro que el EventDispatcher tenga un user = NULL, para que no registre user incorrecto.
                resolve(TimelineEventsDispatcherService::class)->setLoginUser(null);

                $lead = $this->assignLeadUser($lead, $automation);
                $this->addLeadTags($lead, $automation);
                $this->addLeadTask($lead, $automation);
                $this->addLeadNote($lead, $automation);
                $this->assignStatus($lead, $automation);
                $this->assignLeadQuality($lead, $automation);
                $this->addAcquisitionChannel($lead, $automation);
                $this->assignLeadNotificationEmail($lead, $automation);
                $this->assignLeadCustomFieldValuesByMapping($lead, $automation);
                $this->assignLeadNotificationWhatsAppMessages($lead, $automation);

                // If an email was sent, it returns automationLog directly, if not, it returns null.
                $automationLog = $this->sendEmailAndStoreLog($lead, $automation);
                if (!$automationLog) {
                    $this->createAutomationLog($applied = true, $lead, $automation, $lead->user_id);
                }
                $appliedAutomations->push($automation);
            }
        }

        return $appliedAutomations;
    }


    public function findAutomationsByClientId(int $clientId): Collection
    {
        return $this->automationNewLeadRepository->findAutomationsByClientId($clientId);
    }


    public function getFlowChartMarkdownString(AutomationNewLead $automation): string
    {
        $markdown = $this->mermaidChartHelper->buildAutomationNewLeadMarkdown($automation);
        return $markdown;
    }


    public function leadMatchesAutomation(Lead $lead, AutomationNewLead $automation)
    {
        $leadTypeMatches = $this->triggeringLeadTypeMatchesLead($lead, $automation);
        if (!$leadTypeMatches) {
            return false;
        }
        $landingMatches = $this->triggeringLandingsMatchesLead($lead, $automation);
        if (!$landingMatches) {
            return false;
        }
        $fieldsMatch = $this->formFieldMatchesLead($lead, $automation);
        if (!$fieldsMatch) {
            return false;
        }
        $utmsMatch = $this->utmParameterMatchesLead($lead, $automation);
        if (!$utmsMatch) {
            return false;
        }
        $trackingParamsMatch = $this->trackingParameterMatchesLead($lead, $automation);
        if (!$trackingParamsMatch) {
            return false;
        }
        $customFieldsMatch = $this->leadCustomFieldsMatchMatchesLead($lead, $automation);
        if (!$customFieldsMatch) {
            return false;
        }
        $hasRepeatedEmailMatches = $this->hasRepeatedEmailMatches($lead, $automation);
        if (!$hasRepeatedEmailMatches) {
            return false;
        }
        $hasRepeatedPhoneMatches = $this->hasRepeatedPhoneMatches($lead, $automation);
        if (!$hasRepeatedPhoneMatches) {
            return false;
        }

        return true;
    }


    public function addLeadTags(Lead $lead, AutomationNewLead $automation): Lead
    {
        $leadTags = $lead->tags->fresh();
        $tagsToAdd = $automation->tagsToAdd;
        $tagsToAdd = $tagsToAdd->filter(function ($tagToAdd) use ($leadTags) {
            $alreadyAsssignedTag = $leadTags->where('id', $tagToAdd->id)->first();
            return $alreadyAsssignedTag ? false : true;
        });
        $allTags = $leadTags->merge($tagsToAdd);
        if ($allTags) {
            $this->actionsLeadService->setLeadTags($lead, $allTags);
        }
        return $lead;
    }


    public function assignStatus(Lead $lead, AutomationNewLead $automation): Lead
    {
        if (!$automation->status_id_to_assign || !$automation->statusToAssign) {
            return $lead;
        }
        $this->actionsLeadService->changeStatus($lead, $automation->statusToAssign);
        return $lead->fresh();
    }


    protected function addAcquisitionChannel(Lead $lead, AutomationNewLead $automation): Lead
    {
        if (!$automation->add_acquisition_channel_id || !$automation->acquisitionChannelToAdd) {
            return $lead;
        }
        $this->actionsLeadService->changeAcquisitionChannel($lead, $automation->acquisitionChannelToAdd);
        return $lead->fresh();
    }


    protected function addLeadTask(Lead $lead, AutomationNewLead $automation)
    {
        if ($automation->add_new_task) {
            $daysToExpire = $automation->new_task_days_to_expire ?? 1;
            $attr = [
                'lead_id' => $lead->id,
                'client_id' => $lead->client_id,
                'user_id' => $lead->user->id,
                'title' => $automation->new_task_title,
                'description' => $automation->new_task_description,
                'limit_date' => new DateTime('+' . $daysToExpire . ' days'),
            ];
            $this->taskService->create($attr);
        }
    }


    protected function addLeadNote(Lead $lead, AutomationNewLead $automation): Lead
    {
        if ($automation->add_new_note) {
            $data = [
                'user_id' => $lead->user_id,
                'client_id' => $lead->client_id,
                'text' => $automation->new_note_text,
            ];
            $this->noteService->create($lead, $data);
        }
        return $lead;
    }


    protected function assignLeadUser(Lead $lead, AutomationNewLead $automation): Lead
    {
        if (!$automation->assign_user_ids) {
            return $lead;
        }
        $userToAssign = $this->userService->findUserToAssignByAutomationNewLead(
            $lead->client, $automation
        );
        if (!$userToAssign) {
            report(new Exception('User to assign not found at UserService::findUserToAssignByAutomationNewLead'));
            return $lead;
        }
        $oldUserArr = $lead->user->toArray();
        $updatedLead = $this->leadService->update($lead, ['user_id' => $userToAssign->id]);
        resolve(TimelineEventsDispatcherService::class)->leadUserUpdated($updatedLead, $oldUserArr, $userToAssign);
        // @todo, acá actualizar leadNotifWhatsAppMessage y leadNotifEmailMessage, y setear el nuevo user_id
        
        return $updatedLead;
    }


    protected function assignLeadQuality(Lead $lead, AutomationNewLead $automation): Lead
    {
        if ($automation->assign_quality) {
            $this->leadService->update($lead, ['quality' => $automation->assign_quality]);
        }
        return $lead;
    }


    protected function assignLeadNotificationEmail(Lead $lead, AutomationNewLead $automation): Lead
    {
        $leadNotifEmail = $lead->leadNotificationEmail;
        if ($leadNotifEmail) {
            if ($automation->do_not_send_email) {
                $this->leadNotificationEmailService->markToDoNotSendByAutomationNewLead($leadNotifEmail, $automation);
            } elseif ($automation->send_grouped_email) {
                $this->leadNotificationEmailService->markToSendGroupedByAutomationNewLead($leadNotifEmail, $automation);
            }
        }
        return $lead;
    }


    protected function assignLeadNotificationWhatsAppMessages(Lead $lead, AutomationNewLead $automation): Lead
    {
        $leadNotifWhatsAppMessage = $lead->leadNotificationWhatsAppMessage;
        if ($leadNotifWhatsAppMessage) {
            if ($automation->do_not_send_whatsapp_message) {
                $this->leadNotificationWhatsAppMessageService->markToDoNotSendByAutomationNewLead(
                    $leadNotifWhatsAppMessage, $automation
                );
            } elseif ($automation->send_grouped_whatsapp_message) {
                $this->leadNotificationWhatsAppMessageService->markToSendGroupedByAutomationNewLead(
                    $leadNotifWhatsAppMessage, $automation
                );
            }
        }
        return $lead;
    }


    protected function assignLeadCustomFieldValuesByMapping(Lead $lead, AutomationNewLead $automation): Lead
    {
        $leadCustomFieldsMapping = $automation->leadCustomFieldsMapping;
        if ($leadCustomFieldsMapping->isEmpty()) {
            return $lead;
        }
        if (!$lead->client->clientSettings->enable_leads_custom_fields) {
            return $lead;
        }
        
        $leadFormFields = $lead->serialized_fields ? json_decode($lead->serialized_fields, true) : [];

        $leadIsFromAPI = $lead->is_from_integration_api ||
            $lead->is_from_zapier_app ||
            $lead->is_from_zapier_webhook ||
            $lead->is_from_make_app
        ;
        $leadIsFromFacebookForm = $lead->is_facebook_form;
        $leadIsFromWhatsAppForm = $lead->is_whatsapp_form;
        if ($leadIsFromAPI || $leadIsFromWhatsAppForm || $leadIsFromFacebookForm) {
            $leadFormFields = [];
        }
        foreach (collect($lead->other_fields) as $otherFieldArr) {
            $fieldName = $otherFieldArr['name'];
            $fieldVal = $otherFieldArr['value'];
            if (!$fieldName) {
                continue;
            }
            if (!isset($leadFormFields[$fieldName])) {
                $leadFormFields[$fieldName] = [];
            }
            if (!is_array($leadFormFields[$fieldName])) {
                $leadFormFields[$fieldName] = [$leadFormFields[$fieldName]];
            }
            array_push($leadFormFields[$fieldName], $fieldVal);
        }

        
        foreach ($leadCustomFieldsMapping as $automationNewLeadCustomFieldMapping) {
            $leadCustomField = $automationNewLeadCustomFieldMapping->leadCustomField;
            // Por las dudas: si fue borrado el custom field, no rompe pero no lo mapea tampoco.
            if (!$leadCustomField) {
                continue;
            }
            $formFieldName = $automationNewLeadCustomFieldMapping->form_field_name;
            $fixedFormFieldName = trim(strtolower($formFieldName));
            $formFieldValue = $leadFormFields[$formFieldName] ?? $leadFormFields[$fixedFormFieldName] ?? null;
            if (is_array($formFieldValue)) {
                $formFieldValue = $formFieldValue[0];
            }
            if ($formFieldValue === null || !is_string($formFieldValue)) {
                continue;
            }

            // $formFieldValue = trim(strtolower($formFieldValue));
            $formFieldValue = trim($formFieldValue);
            $this->leadCustomFieldService->setValue($lead, $leadCustomField, $formFieldValue, $lead->client);
        }

        return $lead;
    }


    protected function triggeringLandingsMatchesLead(Lead $lead, AutomationNewLead $automation): bool
    {
        if (!$automation->triggering_landing_ids) {
            return true;
        }
        $landings = $automation->triggeringLandings;
        foreach ($landings as $landing) {
            if ($landing->id == $lead->landing_id) {
                return true;
            }
        }
        return false;
    }


    public function hasRepeatedEmailMatches(Lead $lead, AutomationNewLead $automation): bool
    {
        if (!$automation->trigger_if_email_repeatead) {
            return true;
        }
        if ($lead->leadContactEmails->isEmpty()) {
            return false;
        }
        $count = $this->leadContactEmailService->countRepeatedEmailsInOtherLeads($lead);
        return $count ? true : false;
    }


    public function hasRepeatedPhoneMatches(Lead $lead, AutomationNewLead $automation): bool
    {
        if (!$automation->trigger_if_phone_repeatead) {
            return true;
        }
        if ($lead->leadContactPhones->isEmpty()) {
            return false;
        }
        $count = $this->leadContactPhoneService->countRepeatedPhonesInOtherLeads($lead);
        return $count ? true : false;
    }


    protected function triggeringLeadTypeMatchesLead(Lead $lead, AutomationNewLead $automation): bool
    {
        $triggerType = $automation->triggering_lead_type;
        $leadIsManual = $lead->is_manually_created;
        $leadIsManualBulk = $lead->is_bulk_created;
        $leadIsManualIndividual = $lead->is_manually_created && !$lead->is_bulk_created;

        if ($leadIsManual) {
            if ($triggerType == 'manual') {
                return true;
            }
            if ($triggerType == 'manual_bulk') {
                return $leadIsManualBulk;
            }
            if ($triggerType == 'manual_individual') {
                return $leadIsManualIndividual;
            }
            return false;
        }

        $leadIsFromAPI = $lead->is_from_integration_api ||
            $lead->is_from_zapier_app ||
            $lead->is_from_zapier_webhook ||
            $lead->is_from_make_app
        ;
        if ($leadIsFromAPI) {
            if ($triggerType == 'api') {
                return true;
            }
            return false;
        }

        $leadIsForm = $lead->method == 'form';
        $leadIsFBForm = $leadIsForm && $lead->is_facebook_form;
        $leadIsWhatsAppForm = $leadIsForm && $lead->is_whatsapp_form;
        $leadIsWebChat = $lead->method == 'chat' && !$lead->is_wap_bot_chat;
        $leadIsWapBotChat = $lead->method == 'chat' && $lead->is_wap_bot_chat;
        $leadIsWebForm = $leadIsForm && !$leadIsWhatsAppForm && !$leadIsFBForm;

        if ($triggerType == 'form_or_chat') {
            return $leadIsForm || $leadIsWebChat || $leadIsWapBotChat;
        }
        if ($triggerType == 'chat' && $leadIsWebChat) {
            return true;
        }
        if ($triggerType == 'wap_bot_chat' && $leadIsWapBotChat) {
            return true;
        }
        if ($triggerType == 'form') {
            return $leadIsForm;
        }
        if ($triggerType == 'web_form') {
            return $leadIsWebForm;
        }
        if ($triggerType == 'facebook_form') {
            return $leadIsFBForm;
        }
        if ($triggerType == 'whatsapp_form') {
            return $leadIsWhatsAppForm;
        }
        return false;
    }


    private function sendEmailAndStoreLog(Lead $lead, AutomationNewLead $automation): ?AutomationLog
    {
        $sendAskPhoneEmail = !$lead->main_phone && $automation->auto_reply_ask_phone_email_template_id;
        $sendAutoReplyEmail = !$sendAskPhoneEmail && $automation->auto_reply_email_template_id;

        if ($lead->client->clientSettings->email_sending_blocked) {
            return null;
        }

        if ($sendAskPhoneEmail) {
            $emailTemplate = $automation->askPhoneEmailTemplate;
            $automationLog = $this->sendEmailByTemplateAndStoreLog($emailTemplate, $lead, $automation);
        } elseif ($sendAutoReplyEmail) {
            $emailTemplate = $automation->autoReplyEmailTemplate;
            $automationLog = $this->sendEmailByTemplateAndStoreLog($emailTemplate, $lead, $automation);
        }
        return $automationLog ?? null;
    }


    private function isLeadUserVerifiedToSendEmails(Lead $lead): bool
    {
        $user = $lead->user;
        return ($user->email_is_verified && $user->email_from_address && $user->email_from_name);
    }


    private function sendEmailByTemplateAndStoreLog(
        EmailTemplate $emailTemplate,
        Lead $lead,
        AutomationNewLead $automation
    ): ?AutomationLog {
        $isTimeToSendEmail = $this->isTimeToSendReplyEmail($automation);
        $notSendOutOfHour = $automation->auto_reply_do_not_send_out_of_hour;
        // Si NO está en horario y NO está habilitado para enviar fuera de horario,
        // entonces NO se envía ningún email (ni se programa).
        if (!$isTimeToSendEmail && $notSendOutOfHour) {
            return null;
        }
        $sendDate = $this->getDateNow();
        // Si NO está en horario y SI está habilitado para programar cuando está fuera de horario.
        // entonces lo programo para hoy a la hora minima, o mañana a la hora mínima (si existe, si no 00 hs).
        if (!$isTimeToSendEmail) {
            $hourNow = (int) ($this->getDateNow())->format('H');
            $minHour = (int) ($automation->auto_reply_send_min_hour ?? 0);
            if ($minHour && $hourNow < $minHour) {
                $sendDate = ($this->getDateNow())->setTime($minHour, 0, 0);
            } else {
                $sendDate = (new DateTime('tomorrow'))->setTime($minHour, 0, 0);
            }
        }

        $automationLog = $this->createAutomationLog($applied = true, $lead, $automation);
        if (!$this->isLeadUserVerifiedToSendEmails($lead)) {
            $this->markAutomationLogAsNotApplied($automationLog);
            $this->notificationService->storeAutomationEmailSendingEmailError($automationLog);
            return $automationLog;
        }

        try {
            DB::beginTransaction();
            $this->sendEmail($lead, $emailTemplate, $automationLog, $sendDate);
            DB::commit();
        } catch (EmailSendValidationUserNotEnabledException $e) {
            DB::rollBack();
            $this->markAutomationLogAsNotApplied($automationLog);
            $this->notificationService->storeAutomationEmailSendingEmailError($automationLog);
            report($e);
        } catch (Throwable $e) {
            // @TODO aca no se hace nada, habría que hacer algo
            DB::rollBack();
            throw $e;
        }
        return $automationLog;
    }


    /**
     * @throws EmailSendValidationUserNotEnabledException
     */
    private function sendEmail(
        Lead $lead,
        EmailTemplate $emailTemplate,
        AutomationLog $automationLog,
        DateTime $sendTime
    ): Collection {
        $emailScheduleParamsDTO = $this->buildEmailScheduleParamsDTO(
            $lead->user, $emailTemplate, $automationLog, $sendTime
        );
        $this->emailService->setRequestUser($lead->user);
        $emails = $this->emailService->scheduleToLead($lead, $emailScheduleParamsDTO);
        return $emails;
    }


    private function buildEmailScheduleParamsDTO(
        User $user,
        EmailTemplate $emailTemplate,
        AutomationLog $automationLog,
        DateTime $sendTime
    ): EmailScheduleParametersDTO {
        $scheduleParamsDTO = new EmailScheduleParametersDTO();
        $scheduleParamsDTO->automationLog = $automationLog;
        $scheduleParamsDTO->body = $emailTemplate->body;
        $scheduleParamsDTO->subject = $emailTemplate->subject;
        $scheduleParamsDTO->sendDate = $sendTime->format('Y-m-d\TH:i:sP');
        $scheduleParamsDTO->isProposal = $emailTemplate->is_proposal;
        $scheduleParamsDTO->attachments = $emailTemplate->attachments;

        $emailSign = $this->userService->getEmailSign($user);
        if ($emailSign) {
            $scheduleParamsDTO->body = $scheduleParamsDTO->body . $emailSign;
        }
        return $scheduleParamsDTO;
    }


    private function isTimeToSendReplyEmail(AutomationNewLead $automation): bool
    {
        $minHour = $automation->auto_reply_send_min_hour;
        $maxHour = $automation->auto_reply_send_max_hour;
        if (!$minHour && !$maxHour) {
            return true;
        }
        
        $clientTz = new DateTimeZone($automation->client->timezone);
        $clientNow = ($this->getDateNow())->setTimezone($clientTz);
        $day = (int) $clientNow->format('d');
        $year = (int) $clientNow->format('Y');
        $month = (int) $clientNow->format('m');

        $minHourDateTime = null;
        $maxHourDateTime = null;
        if ($minHour) {
            $minHourDateTime = ($this->getDateNow())->setTime((int) $minHour, 0)->setTimezone($clientTz);
            $minHourDateTime->setDate($year, $month, $day); // Por si cambió de día al setear la hora.
        }
        if ($maxHour) {
            $maxHourDateTime = ($this->getDateNow())->setTime((int) $maxHour, 0)->setTimezone($clientTz);
            $maxHourDateTime->setDate($year, $month, $day); // Por si cambió de día al setear la hora.
        }
        if ($minHourDateTime && $maxHourDateTime && $clientNow >= $minHourDateTime && $clientNow <= $maxHourDateTime) {
            return true;
        }
        if (!$maxHourDateTime && $minHourDateTime && $clientNow >= $minHourDateTime) {
            return true;
        }
        if (!$minHourDateTime && $maxHourDateTime && $clientNow <= $maxHourDateTime) {
            return true;
        }
        return false;
    }


    private function createAutomationLog(bool $fullyApplied, Lead $lead, AutomationNewLead $automation): AutomationLog
    {
        $userIdsToAssign = $automation->assign_user_ids ?? [];
        $assignedUserId = null;
        if (in_array($lead->user_id, $userIdsToAssign)) {
            $assignedUserId = $lead->user_id;
        }
        return $this->automationLogService->createAutomationNewLeadLog(
            $fullyApplied, $lead, $automation, $assignedUserId
        );
    }


    private function markAutomationLogAsNotApplied(AutomationLog $automationLog): AutomationLog
    {
        return $this->automationLogService->markAsNotApplied($automationLog);
    }


    public function formFieldMatchesLead(Lead $lead, AutomationNewLead $automation)
    {
        $automationFields = $automation->formFieldsToMatch;
        // if fields are empty just return true
        if ($automationFields->isEmpty()) {
            return true;
        }

        // loop through fields to match and check if the
        // serialized form has some field and then match by the rule (gte, lte, eq, neq)
        $leadFormFields = json_decode($lead->serialized_fields, true);
        foreach ($automationFields as $automationField) {
            $originalFieldName = $automationField->field_name;
            $fixedFieldName = trim(strtolower($automationField->field_name));
            if (isset($leadFormFields[$originalFieldName]) || isset($leadFormFields[$fixedFieldName])) {
                // get the lead form value
                $leadFormValue = $leadFormFields[$originalFieldName] ?? $leadFormFields[$fixedFieldName];
                // loop through the automation values and compare
                foreach ($automationField->field_values as $fieldValue) {
                    if (is_array($leadFormValue)) {
                        $val = array_pop($leadFormValue);
                        if (is_array($val)) {
                            $leadFormValue = (string) array_pop($val);
                        } else {
                            $leadFormValue = (string) $val;
                        }
                    }
                    $leadFormValue = $this->cleanWord($leadFormValue);
                    $fieldValue = $this->cleanWord($fieldValue);

                    if ($automationField->isEqual && $leadFormValue == $fieldValue) {
                        return true;
                    }
                    if ($automationField->isLessThanEqual && $leadFormValue <= $fieldValue) {
                        return true;
                    }
                    if ($automationField->isGreaterThanEqual && $leadFormValue >= $fieldValue) {
                        return true;
                    }
                    if ($automationField->isNotEqual &&  $leadFormValue != $fieldValue) {
                        return true;
                    }
                }
            }
        }

        // Same with other_fields
        // @todo fixearlo, quitar este duplicado hecho para salir del apuro
        $leadFormFields = [];
        foreach (collect($lead->other_fields) as $otherFieldArr) {
            $fieldVal = $otherFieldArr['value'];
            $fieldName = $otherFieldArr['name'] ?? null;
            if ($fieldName === null || trim((string) $fieldName) === '') {
                continue;
            }
            if (!isset($leadFormFields[$fieldName])) {
                $leadFormFields[$fieldName] = [];
            }
            array_push($leadFormFields[$fieldName], $fieldVal);
        }
        // $leadFormFields = collect($lead->other_fields)->keyBy('name')->map(function ($f) {
        //     return $f['value'];
        // })->toArray();

        foreach ($automationFields as $automationField) {
            $originalFieldName = $automationField->field_name;
            $fixedFieldName = trim(strtolower($automationField->field_name));
            if (isset($leadFormFields[$originalFieldName]) || isset($leadFormFields[$fixedFieldName])) {
                // get the lead form value
                $leadFormValues = $leadFormFields[$originalFieldName] ?? $leadFormFields[$fixedFieldName];

                foreach ($leadFormValues as $leadFormValue) {
                    // loop through the automation values and compare
                    foreach ($automationField->field_values as $fieldValue) {
                        if (is_array($leadFormValue)) {
                            $leadFormValue = (string) array_pop($leadFormValue);
                        }
                        $leadFormValue = $this->cleanWord($leadFormValue);
                        $fieldValue = $this->cleanWord($fieldValue);

                        if ($automationField->isEqual && $leadFormValue == $fieldValue) {
                            return true;
                        }
                        if ($automationField->isLessThanEqual && $leadFormValue <= $fieldValue) {
                            return true;
                        }
                        if ($automationField->isGreaterThanEqual && $leadFormValue >= $fieldValue) {
                            return true;
                        }
                        if ($automationField->isNotEqual &&  $leadFormValue != $fieldValue) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }


    public function utmParameterMatchesLead(Lead $lead, AutomationNewLead $automation): bool
    {
        // if utms parameters to match are empty just return true
        if ($automation->utmParametersToMatch->isEmpty()) {
            return true;
        }

        foreach ($automation->utmParametersToMatch as $automationNewLeadParameter) {
            $utmName = trim(strtolower($automationNewLeadParameter->utm_name));
            if (!isset($lead[$utmName])) {
                continue;
            }
            $leadUtmValue = $lead->$utmName ? trim(strtolower($lead->$utmName)) : null;
            if (!$leadUtmValue) {
                continue;
            }

            foreach ($automationNewLeadParameter->utm_values as $utmValue) {
                $automationUtmValue = trim(strtolower($utmValue));
                // Puede ser utm_source, utm_medium, etc...
                if ($automationNewLeadParameter->isEqual &&  $leadUtmValue == $automationUtmValue) {
                    return true;
                }

                $leadUtmValue = (float) $leadUtmValue;
                $automationUtmValue = (float) $automationUtmValue;
                if ($automationNewLeadParameter->isLessThanEqual && $leadUtmValue <= $automationUtmValue) {
                    return true;
                }
                if ($automationNewLeadParameter->isGreaterThanEqual && $leadUtmValue >= $automationUtmValue) {
                    return true;
                }
                if ($automationNewLeadParameter->isNotEqual &&  $leadUtmValue != $automationUtmValue) {
                    return true;
                }
            }
        }
        return false;
    }


    public function trackingParameterMatchesLead(Lead $lead, AutomationNewLead $automation): bool
    {
        $trackingParametersToMatch = $automation->trackingParametersToMatch;
        if ($trackingParametersToMatch->isEmpty()) {
            return true;
        }

        // Si la automation tiene condiciones de tracking pero el lead no tiene tracking_parameters,
        // no matchea (consistente con form fields).
        $leadTrackingParameters = $lead->tracking_parameters;
        if (!$leadTrackingParameters || !is_array($leadTrackingParameters)) {
            return false;
        }

        foreach ($trackingParametersToMatch as $automationTrackingParameter) {
            $parameterName = $automationTrackingParameter->tracking_parameter_name;
            // Lookup literal/plano: la clave puede contener puntos (ej: "welcome_message.text").
            if (!array_key_exists($parameterName, $leadTrackingParameters)) {
                continue;
            }
            $leadValue = $leadTrackingParameters[$parameterName];
            if ($leadValue === null || !is_scalar($leadValue)) {
                continue;
            }
            $leadValue = $this->cleanWord((string) $leadValue);

            foreach ($automationTrackingParameter->tracking_parameter_values as $automationValue) {
                $automationValue = $this->cleanWord((string) $automationValue);
                if ($automationTrackingParameter->isEqual && $leadValue == $automationValue) {
                    return true;
                }
                if ($automationTrackingParameter->isNotEqual && $leadValue != $automationValue) {
                    return true;
                }
                if ($automationTrackingParameter->isLessThanEqual && $leadValue <= $automationValue) {
                    return true;
                }
                if ($automationTrackingParameter->isGreaterThanEqual && $leadValue >= $automationValue) {
                    return true;
                }
            }
        }
        return false;
    }


    private function leadCustomFieldsMatchMatchesLead(Lead $lead, AutomationNewLead $automation): bool
    {
        $leadCustomFieldsMatches = $automation->leadCustomFieldsMatch;
        // si no hay condiciones, matchea
        if ($leadCustomFieldsMatches->isEmpty()) {
            return true;
        }
        // si el feature no está habilitado para el cliente, no bloquea
        if (!$lead->client->clientSettings->enable_leads_custom_fields) {
            return true;
        }

        foreach ($leadCustomFieldsMatches as $leadCustomFieldMatch) {
            $leadCustomField = $leadCustomFieldMatch->leadCustomField;
            if (!$leadCustomField) {
                // si el campo fue borrado, ignoro esta fila
                continue;
            }
            $leadCustomFieldValueModel = $lead->leadCustomFieldsValues()
                ->where('lead_custom_field_id', $leadCustomField->id)
                ->first()
            ;
            $leadFieldValue = $leadCustomFieldValueModel?->value;
            if ($leadFieldValue === null || $leadFieldValue === '') {
                continue;
            }
            $leadFieldValue = $this->cleanWord((string) $leadFieldValue);
            
            foreach ($leadCustomFieldMatch->field_values as $automationValueToMatch) {
                $automationValueToMatch = $this->cleanWord((string) $automationValueToMatch);
                if ($leadCustomFieldMatch->isEqual && $leadFieldValue == $automationValueToMatch) {
                    return true;
                }
            }
        }
        return false;
    }


    private function cleanWord($sentence)
    {
        $sentence = trim($sentence);
        // clean accents
        $sentence = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n'],
            $sentence
        );
        // remove special chars and trim the empty spaces
        $sentence = preg_replace('/[^a-zA-Z0-9]/', '', $sentence);
        return strtolower($sentence);
    }


    private function automationFormFieldsChanged(AutomationNewLead $automation, AutomationNewLeadDTO $dto): bool
    {
        // dd($dto->formFields, $automation->formFieldsToMatch->first()->field_values);
        $dtoFormFieldsCollection = collect($dto->formFields);

        if ($dtoFormFieldsCollection->count() !== $automation->formFieldsToMatch->count()) {
            return true;
        }
        foreach ($automation->formFieldsToMatch as $formField) {
            $automationFieldName = $formField->field_name;
            $automationFieldValues = $formField->field_values;
            $dtoFormField = $dtoFormFieldsCollection->where('field_name', $automationFieldName)->first();
            if (!$dtoFormField) {
                return true;
            }
            $dtoFormFieldValues = $dtoFormField['field_values'];
            if ($dtoFormFieldValues !== $automationFieldValues) {
                return true;
            }
        }
        return false;
    }


    private function automationUtmParametersChanged(AutomationNewLead $automation, AutomationNewLeadDTO $dto): bool
    {
        $dtoUtmParametersCollection = collect($dto->utmParameters);

        if ($dtoUtmParametersCollection->count() !== $automation->utmParametersToMatch->count()) {
            return true;
        }
        foreach ($automation->utmParametersToMatch as $utmParameter) {
            $automationUtmName = $utmParameter->utm_name;
            $automationUtmValues = $utmParameter->utm_values;
            $dtoUtmParameter = $dtoUtmParametersCollection->where('utm_name', $automationUtmName)->first();
            if (!$dtoUtmParameter) {
                return true;
            }
            $dtoUtmParameterValues = $dtoUtmParameter['utm_values'];
            if ($dtoUtmParameterValues !== $automationUtmValues) {
                return true;
            }
        }
        return false;
    }


    private function trackingParametersChanged(AutomationNewLead $automation, AutomationNewLeadDTO $dto): bool
    {
        $dtoTrackingParametersCollection = collect($dto->trackingParameters);

        if ($dtoTrackingParametersCollection->count() !== $automation->trackingParametersToMatch->count()) {
            return true;
        }
        foreach ($automation->trackingParametersToMatch as $trackingParameter) {
            $automationParameterName = $trackingParameter->tracking_parameter_name;
            $automationParameterValues = $trackingParameter->tracking_parameter_values;
            $dtoTrackingParameter = $dtoTrackingParametersCollection
                ->where('tracking_parameter_name', $automationParameterName)
                ->first()
            ;
            if (!$dtoTrackingParameter) {
                return true;
            }
            $dtoTrackingParameterValues = $dtoTrackingParameter['tracking_parameter_values'];
            if ($dtoTrackingParameterValues !== $automationParameterValues) {
                return true;
            }
        }
        return false;
    }


    private function leadCustomFieldsMappingChanged(AutomationNewLead $automation, AutomationNewLeadDTO $dto): bool
    {
        $dtoMapping = collect($dto->leadCustomFieldsMapping);
        if ($dtoMapping->count() !== $automation->leadCustomFieldsMapping->count()) {
            return true;
        }

        foreach ($automation->leadCustomFieldsMapping as $autLeadCustomFieldMapping) {
            // Si fue borrado desde configuracion -> campos personalizados
            if (!$autLeadCustomFieldMapping->leadCustomField) {
                return true;
            }
            $leadCustomFieldId = $autLeadCustomFieldMapping->leadCustomField->id;
            $dtoCustomFieldMapping = $dtoMapping->where('lead_custom_field_id', $leadCustomFieldId)->first();
            if (!$dtoCustomFieldMapping) {
                return true;
            }

            $formFieldName = $autLeadCustomFieldMapping->form_field_name;
            $dtoFormFieldName = $dtoCustomFieldMapping['form_field_name'];
            if ($formFieldName !== $dtoFormFieldName) {
                return true;
            }
        }
        return false;
    }


    private function leadCustomFieldsMatchChanged(AutomationNewLead $automation, AutomationNewLeadDTO $dto): bool
    {
        $dtoMatch = collect($dto->leadCustomFieldsMatch);
        if ($dtoMatch->count() !== $automation->leadCustomFieldsMatch->count()) {
            return true;
        }
        foreach ($automation->leadCustomFieldsMatch as $autLeadCustomFieldMatch) {
            // Si fue borrado el custom field
            if (!$autLeadCustomFieldMatch->leadCustomField) {
                return true;
            }
            $leadCustomFieldId = $autLeadCustomFieldMatch->leadCustomField->id;
            $dtoMatchItem = $dtoMatch->firstWhere('lead_custom_field_id', $leadCustomFieldId);
            if (!$dtoMatchItem) {
                return true;
            }
            $dtoExpression = $dtoMatchItem['expression'] ?? 'eq';
            if (($autLeadCustomFieldMatch->expression ?? 'eq') !== $dtoExpression) {
                return true;
            }
            $dtoValues = $dtoMatchItem['field_values'] ?? [];
            if ($dtoValues !== $autLeadCustomFieldMatch->field_values) {
                return true;
            }
        }
        return false;
    }


    // Unifico esto acá para poder hacer pruebas cambiando la "fecha actual"
    private function getDateNow(): DateTime
    {
        // return environmentIsNotProduction() ? new DateTime('2022-11-09 15:00:00') : new DateTime('now');
        return new DateTime('now');
    }

}
