<?php

namespace App\Http\Requests\Automations;

use DateTime;
use DateTimeZone;
use App\Models\Tag;
use App\Models\User;
use App\Models\Status;
use App\Models\Landing;
use App\Models\EmailTemplate;
use App\Services\API\UserService;
use App\Models\AcquisitionChannel;
use App\Http\Requests\APIBaseRequest;
use App\DTO\Automations\AutomationNewLeadDTO;
use App\Rules\InAutomationNewLeadReturnFields;


class CreateAutomationNewLeadRequest extends APIBaseRequest
{

    private $addTags = null;
    private $assignUserIds = [];
    private $statusToAssign = null;
    private $triggeringLandings = null;
    private $autoReplyEmailTemplate = null;
    private $acquisitionChannelToAdd = null;
    private $autoReplyAskPhoneEmailTemplate = null;


    public function rules()
    {
        $triggerTypeFieldNames = [
            'api',
            'chat',
            'form',
            'manual',
            'web_form',
            'manual_bulk',
            'wap_bot_chat',
            'form_or_chat',
            'facebook_form',
            'whatsapp_form',
            'manual_individual',
        ];
        return [
            'trigger_if_email_repeatead' => ['required', 'boolean'],
            'trigger_if_phone_repeatead' => ['required', 'boolean'],
            'triggering_landing_ids' => ['sometimes', 'nullable', 'array'],
            'triggering_lead_type' => ['required', 'in:' . implode(',', $triggerTypeFieldNames)],
            'add_tags_ids' => ['present', 'nullable', 'array'],
            'status_id_to_assign' => ['present', 'nullable', 'integer'],
            'add_acquisition_channel_id' => ['present', 'nullable', 'integer'],
            'assign_user_ids' => ['present', 'nullable', 'array'],
            'assign_quality' => ['present', 'nullable', 'integer', 'min:1', 'max:3'],
            'do_not_send_email' => ['present', 'boolean'],
            'send_grouped_email' => ['present', 'boolean'],
            'do_not_send_whatsapp_message' => ['present', 'boolean'],
            'send_grouped_whatsapp_message' => ['present', 'boolean'],
            'grouped_whatsapp_message_text' => ['present', 'nullable', 'string'],
            'grouped_email_subject' => ['present', 'nullable', 'string'],
            'grouped_email_body' => ['present', 'nullable', 'string'],
            'auto_reply_ask_phone_email_template_id' => ['present', 'nullable', 'integer'],
            'auto_reply_email_template_id' => ['present', 'nullable', 'integer'],
            'auto_reply_send_min_hour' => ['present', 'nullable', 'string', 'digits_between:00,23'],
            'auto_reply_send_max_hour' => ['present', 'nullable', 'string', 'digits_between:00,23'],
            'auto_reply_do_not_send_out_of_hour' => ['present', 'nullable', 'boolean'],
            'add_new_task' => ['present', 'boolean'],
            'new_task_title' => ['present', 'nullable', 'string'],
            'new_task_description' => ['present', 'nullable', 'string'],
            'new_task_days_to_expire' => ['present', 'nullable', 'integer'],
            'add_new_note' => ['present', 'boolean'],
            'new_note_text' => ['present', 'nullable', 'string'],
            'application_order' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'form_fields' => ['sometimes', 'array'],
            'form_fields.*.expression' => ['sometimes', 'string', 'in:eq,neq,gte,lte'],
            'form_fields.*.field_name' => ['sometimes', 'string'],
            'form_fields.*.field_values' => ['sometimes', 'array'],
            'form_fields.*.field_values.*' => ['sometimes', 'string'],
            'utm_parameters' => ['sometimes', 'array'],
            'utm_parameters.*.expression' => ['sometimes', 'string', 'in:eq,neq,gte,lte'],
            'utm_parameters.*.utm_name' => ['sometimes', 'string'],
            'utm_parameters.*.utm_values' => ['sometimes', 'array'],
            'utm_parameters.*.utm_values.*' => ['sometimes', 'string'],
            'tracking_parameters' => ['sometimes', 'array'],
            'tracking_parameters.*.expression' => ['sometimes', 'string', 'in:eq,neq,gte,lte'],
            'tracking_parameters.*.tracking_parameter_name' => ['sometimes', 'string'],
            'tracking_parameters.*.tracking_parameter_values' => ['sometimes', 'array'],
            'tracking_parameters.*.tracking_parameter_values.*' => ['sometimes', 'string'],
            'lead_custom_fields_mapping' => ['sometimes', 'array'],
            'lead_custom_fields_mapping.*.form_field_name' => ['sometimes', 'string'],
            'lead_custom_fields_mapping.*.lead_custom_field_id' => ['sometimes', 'integer'],
            'lead_custom_fields_match' => ['sometimes', 'array'],
            'lead_custom_fields_match.*.expression' => ['sometimes', 'string', 'in:eq'],
            'lead_custom_fields_match.*.lead_custom_field_id' => ['sometimes', 'integer'],
            'lead_custom_fields_match.*.field_values' => ['sometimes', 'array'],
            'lead_custom_fields_match.*.field_values.*' => ['sometimes', 'string'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InAutomationNewLeadReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');

                $landingIds = request()->input('triggering_landing_ids');
                if ($landingIds) {
                    $landings = Landing::where('client_id', $client->id)->whereIn('id', $landingIds)->get();
                    if ($landings->count() != count($landingIds)) {
                        $validator->errors()->add('triggering_landing_ids', 'not_all_triggering_landings_exists');
                        return false;
                    }
                    $this->triggeringLandings = $landings;
                }

                $addTagIds = request()->input('add_tags_ids');
                if ($addTagIds) {
                    $tags = Tag::where('client_id', $client->id)->whereIn('id', $addTagIds)->get();
                    if ($tags->count() != count($addTagIds)) {
                        $validator->errors()->add('add_tags_ids', 'not_all_add_tags_exists');
                        return false;
                    }
                    $this->addTags = $tags;
                }

                $addAcquisitionChannelId = request()->input('add_acquisition_channel_id');
                if ($addAcquisitionChannelId) {
                    $acquisitionChannelToAdd = AcquisitionChannel::where('client_id', $client->id)
                        ->where('id', $addAcquisitionChannelId)
                        ->first()
                    ;
                    if (!$acquisitionChannelToAdd) {
                        $validator->errors()->add('add_acquisition_channel_id', 'acquisition_channel_does_not_exist');
                        return false;
                    }
                    $this->acquisitionChannelToAdd = $acquisitionChannelToAdd;
                }

                $statusIdToAssign = request()->input('status_id_to_assign');
                if ($statusIdToAssign) {
                    $statusToAssign = Status::where('client_id', $client->id)->where('id', $statusIdToAssign)->first();
                    if (!$statusToAssign) {
                        $validator->errors()->add('status_id_to_assign', 'status_does_not_exist');
                        return false;
                    }
                    $this->statusToAssign = $statusToAssign;
                }

                $this->assignUserIds = request()->input('assign_user_ids') ?? [];
                if ($this->assignUserIds) {
                    // $usersToAssign = User::find($this->assignUserIds);
                    $usersToAssign = resolve(UserService::class)->findByClientAndIds($client, $this->assignUserIds);
                    if (count($this->assignUserIds) != $usersToAssign->count()) {
                        $validator->errors()->add('assign_user_ids', 'some_users_to_assign_do_not_exist');
                        return false;
                    }
                    foreach ($usersToAssign as $userToAssign) {
                        if (!$userToAssign->enabled) {
                            $validator->errors()->add('assign_user_ids', 'one_user_to_assign_is_not_enabled');
                            return false;
                        }
                        if (!$userToAssign->enabled_to_receive_leads) {
                            $validator->errors()->add(
                                'assign_user_ids', 'one_user_to_assign_is_not_enabled_to_receive_leads'
                            );
                            return false;
                        }
                    }
                }

                $autoReplyAskPhoneEmailTemplateId = request()->input('auto_reply_ask_phone_email_template_id');
                if ($autoReplyAskPhoneEmailTemplateId) {
                    $emailTemplate = EmailTemplate::where('client_id', $client->id)
                        ->where('id', $autoReplyAskPhoneEmailTemplateId)
                        ->first()
                    ;
                    if (!$emailTemplate) {
                        $validator->errors()->add(
                            'auto_reply_ask_phone_email_template_id',
                            'auto_reply_ask_phone_email_template_does_not_exists'
                        );
                        return false;
                    }
                    $this->autoReplyAskPhoneEmailTemplate = $emailTemplate;
                }

                $autoReplyEmailTemplateId = request()->input('auto_reply_email_template_id');
                if ($autoReplyEmailTemplateId) {
                    $emailTemplate = EmailTemplate::where('client_id', $client->id)
                        ->where('id', $autoReplyEmailTemplateId)
                        ->first()
                    ;
                    if (!$emailTemplate) {
                        $validator->errors()->add('auto_reply_email_template_id', 'email_template_does_not_exist');
                        return false;
                    }
                    $this->autoReplyEmailTemplate = $emailTemplate;
                }

                $doNotSendEmail = request()->input('do_not_send_email');
                $sendGroupedEmail = request()->input('send_grouped_email');
                if ($sendGroupedEmail && $doNotSendEmail) {
                    $validator->errors()->add(
                        'do_not_send_email',
                        'send_grouped_email_and_do_not_send_email_could_not_be_both_true'
                    );
                    return false;
                }
                if ($sendGroupedEmail) {
                    if (!request()->input('grouped_email_body') || !request()->input('grouped_email_subject')) {
                        $validator->errors()->add(
                            'send_grouped_email',
                            'grouped_email_body_and_grouped_email_subject_are_empty'
                        );
                        return false;
                    }
                }

                $doNotSendWhatsAppMessage = request()->input('do_not_send_whatsapp_message');
                $sendGroupedWhatsAppMessage = request()->input('send_grouped_whatsapp_message');
                $groupedWhatsAppMessageText = request()->input('grouped_whatsapp_message_text');
                if ($sendGroupedWhatsAppMessage && $doNotSendWhatsAppMessage) {
                    $validator->errors()->add(
                        'do_not_send_whatsapp_message',
                        'send_grouped_whatsapp_message_and_do_not_send_whatsapp_message_could_not_be_both_true'
                    );
                    return false;
                }
                if ($sendGroupedWhatsAppMessage && !$groupedWhatsAppMessageText) {
                    $validator->errors()->add(
                        'send_grouped_whatsapp_message',
                        'grouped_whatsapp_message_text_are_empty'
                    );
                    return false;
                }

                $addNewTask = request()->input('add_new_task');
                if ($addNewTask) {
                    if (!request()->input('new_task_title')) {
                        $validator->errors()->add('add_new_task', 'new_task_title_is_empty');
                        return false;
                    }
                }

                $addNewNote = request()->input('add_new_note');
                if ($addNewNote) {
                    if (!request()->input('new_note_text')) {
                        $validator->errors()->add('add_new_note', 'new_note_text_is_empty');
                        return false;
                    }
                }

                $formFields = request()->input('form_fields');
                if ($formFields) {
                    foreach ($formFields as $formField) {
                        // Uso strlen para poder pasar por ejemplo "0" como name.
                        if (!$formField['field_values'] || !strlen($formField['field_name'])) {
                            $validator->errors()->add('form_fields', 'form_fields_has_an_empty_field_name_or_value');
                            return false;
                        }
                    }
                }

                $utmParameters = request()->input('utm_parameters');
                if ($utmParameters) {
                    foreach ($utmParameters as $utmParameter) {
                        if (!$utmParameter['utm_values']) {
                            $validator->errors()->add('utm_parameters', 'utm_parameters_has_an_empty_name_or_value');
                            return false;
                        }
                    }
                }

                // Las condiciones de tracking parameters solo aplican a wap_bot_chat.
                // Si llegan con otro triggering_lead_type, las descarto silenciosamente.
                $triggeringLeadType = request()->input('triggering_lead_type');
                if ($triggeringLeadType !== 'wap_bot_chat') {
                    request()->merge(['tracking_parameters' => []]);
                }
                $trackingParameters = request()->input('tracking_parameters');
                if ($trackingParameters) {
                    foreach ($trackingParameters as $trackingParameter) {
                        $name = $trackingParameter['tracking_parameter_name'] ?? null;
                        $values = $trackingParameter['tracking_parameter_values'] ?? null;
                        if (!$values || $name === null || !strlen((string) $name)) {
                            $validator->errors()->add(
                                'tracking_parameters', 'tracking_parameters_has_an_empty_name_or_value'
                            );
                            return false;
                        }
                    }
                }

                $customFieldsMapping = request()->input('lead_custom_fields_mapping');
                if ($customFieldsMapping) {
                    foreach ($customFieldsMapping as $fieldMapping) {
                        if (!$fieldMapping['lead_custom_field_id'] || !$fieldMapping['form_field_name']) {
                            $validator->errors()->add(
                                'lead_custom_fields_mapping', 'lead_custom_fields_mapping_has_an_empty_name_or_value'
                            );
                            return false;
                        }
                    }
                }

                $customFieldsMatch = request()->input('lead_custom_fields_match');
                if ($customFieldsMatch) {
                    foreach ($customFieldsMatch as $fieldMatch) {
                        if (!($fieldMatch['lead_custom_field_id'] ?? null)) {
                            $validator->errors()->add(
                                'lead_custom_fields_match', 'lead_custom_fields_match_has_an_empty_custom_field_id'
                            );
                            return false;
                        }
                        if (!($fieldMatch['field_values'] ?? null)) {
                            $validator->errors()->add(
                                'lead_custom_fields_match', 'lead_custom_fields_match_has_empty_values'
                            );
                            return false;
                        }
                    }
                }
            }
        });
    }


    public function validatedDTO(): AutomationNewLeadDTO
    {
        $val = parent::validated();
        $client = request()->input('client');

        if ($val['auto_reply_send_min_hour'] ?? null) {
            $hour = (int) $val['auto_reply_send_min_hour'];
            $date = (new DateTime())->setTimezone(new DateTimeZone($client->timezone))->setTime($hour, 0, 0);
            $val['auto_reply_send_min_hour'] = ($date->setTimezone(new DateTimeZone('UTC')))->format('H');
        }
        if ($val['auto_reply_send_max_hour'] ?? null) {
            $hour = (int) $val['auto_reply_send_max_hour'];
            $date = (new DateTime())->setTimezone(new DateTimeZone($client->timezone))->setTime($hour, 0, 0);
            $val['auto_reply_send_max_hour'] = ($date->setTimezone(new DateTimeZone('UTC')))->format('H');
        }

        $val['client'] = $client;
        $val['addTags'] = $this->addTags;
        $val['assignUser'] = $this->assignUserIds;
        $val['statusToAssign'] = $this->statusToAssign;
        $val['triggeringLandings'] = $this->triggeringLandings;
        $val['autoReplyEmailTemplate'] = $this->autoReplyEmailTemplate;
        $val['acquisitionChannelToAdd'] = $this->acquisitionChannelToAdd;
        $val['autoReplyAskPhoneEmailTemplate'] = $this->autoReplyAskPhoneEmailTemplate;

        if ($val['triggering_lead_type'] == 'facebook_form') {
            $val['triggeringLandings'] = null;
        }
        if ($val['triggering_lead_type'] == 'manual') {
            $val['triggeringLandings'] = null;
        }

        $dto = AutomationNewLeadDTO::build($val);

        return $dto;
    }

}
