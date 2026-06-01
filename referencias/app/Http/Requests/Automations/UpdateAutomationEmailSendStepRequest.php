<?php

namespace App\Http\Requests\Automations;

use DateTime;
use DateTimeZone;
use App\Models\Tag;
use App\Models\Status;
use App\Models\EmailTemplate;
use App\Rules\IsArrayOfIntegers;
use App\Http\Requests\APIBaseRequest;
use App\Models\AutomationEmailSendStep;
use App\Rules\InAutomationEmailSendStepReturnFields;
use App\DTO\Automations\AutomationEmailSendStepDTO;


class UpdateAutomationEmailSendStepRequest extends APIBaseRequest
{

    private $tagsToAdd;
    private $statusToAdd;
    private $sendEmailTemplate;


    public function rules()
    {
        return [
            'send_delay_days' => ['required', 'integer'],
            'send_email_template_id' => ['required', 'integer'],
            'add_status_id' => ['sometimes', 'integer', 'nullable'],
            'send_hour' => ['sometimes', 'nullable', 'date_format:H:i'],
            'send_delay_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:120'],
            'add_tags_ids' => ['sometimes', 'array', new IsArrayOfIntegers(['canBeEmpty' => true])],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InAutomationEmailSendStepReturnFields()],
        ];
    }

    
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $sendHour = request()->get('send_hour');
                $clientId = request()->input('client')->id;
                $addTagIds = request()->input('add_tags_ids') ?? [];
                $automationEmailSend = request()->automationEmailSend;
                $addStatusId = request()->input('add_status_id') ?? null;
                $sendDelayDays = (int) request()->get('send_delay_days');
                $sendDelayMinutes = (int) request()->get('send_delay_minutes');
                $automationEmailSendStep = request()->automationEmailSendStep;
                $emailTemplateId = request()->input('send_email_template_id');

                if (request()->automationEmailSend->client_id != $clientId) {
                    $validator->errors()->add(
                        'client_id', 'automation_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }

                if (!$sendDelayDays && !$sendDelayMinutes) {
                    $validator->errors()->add('send_delay_days', 'send_delay_minutes_can_not_be_null');
                    return false;
                }
                if ($sendDelayDays && (!$sendHour || $sendDelayMinutes)) {
                    $validator->errors()->add('send_delay_days', 'invalid_days_hour_minutes_combination');
                    return false;
                }

                $conditions = $this->buildAutomationEmailSendStepConditions();
                $existentSteps = AutomationEmailSendStep::where($conditions)->get();
                // Si es 1, es el mismo que se está updateando, si son más es por que ya hay otro.
                if ($existentSteps->count() > 1) {
                    $validator->errors()->add('send_delay_days', 'step_with_that_conditions_already_created');
                    return false;
                }

                $this->sendEmailTemplate = EmailTemplate::where('client_id', $clientId)
                    ->where('id', $emailTemplateId)
                    ->first()
                ;
                if (!$this->sendEmailTemplate) {
                    $validator->errors()->add('send_email_template_id', 'email_template_does_not_exist');
                    return false;
                }

                if ($addTagIds) {
                    $tagsToAdd = Tag::where('client_id', $clientId)->whereIn('id', $addTagIds)->get();
                    if ($tagsToAdd->count() != count($addTagIds)) {
                        $validator->errors()->add('add_tags_ids', 'not_all_tags_exist');
                        return false;
                    }
                    $tagsAreBeingUsed = collect($automationEmailSend->triggering_tags_ids)
                        ->filter(null)
                        ->intersect($addTagIds)
                        ->isNotEmpty()
                    ;
                    if ($tagsAreBeingUsed) {
                        $validator->errors()->add('add_tags_ids', 'tags_to_add_are_triggering_this_sequence');
                        return false;
                    }
                    $this->tagsToAdd = $tagsToAdd;
                }

                if ($addStatusId) {
                    $statusToAdd = Status::where('client_id', $clientId)->where('id', $addStatusId)->first();
                    if (!$statusToAdd) {
                        $validator->errors()->add('add_status_id', 'status_to_add_does_not_exist');
                        return false;
                    }
                    $this->statusToAdd = $statusToAdd;
                }
            }
        });
    }


    public function validatedDTO()
    {
        $val = parent::validated();
        $val['client'] = request()->input('client');
        $val['statusToAdd'] = $this->statusToAdd ?? null;
        $val['sendEmailTemplate'] = $this->sendEmailTemplate;
        $val['tagsToAdd'] = $this->tagsToAdd ?? collect([]);
        $val['automationEmailSend'] = request()->automationEmailSend;
        $val['send_hour'] = $val['send_hour'] ? $this->getFormattedSendHourByTimeZone($val['send_hour']) : null;
        $dto = AutomationEmailSendStepDTO::build($val);
        return $dto;
    }

    
    private function buildAutomationEmailSendStepConditions(): array
    {
        $sendHour = request()->get('send_hour');
        $sendDelayDays = (int) request()->get('send_delay_days');
        $sendDelayMinutes = (int) request()->get('send_delay_minutes');

        $conditions = [
            'client_id' => request()->input('client')->id,
            'send_delay_days' => request()->get('send_delay_days'),
            'automation_email_send_id' => request()->automationEmailSend->id,
        ];
        if ($sendDelayDays) {
            $conditions['send_hour'] = $this->getFormattedSendHourByTimeZone($sendHour);
        } else {
            $conditions['send_delay_minutes'] = $sendDelayMinutes;
        }
        return $conditions;
    }


    private function getFormattedSendHourByTimeZone(string $sendHour): string
    {
        $tz = request()->input('client')->timezone;
        $sendHourArr = explode(':', $sendHour);
        $hour = (int) $sendHourArr[0];
        $minutes = (int) $sendHourArr[1];

        // Set date (with hour and minute) with Client TZ
        $date = (new DateTime())->setTimezone(new DateTimeZone($tz))->setTime($hour, $minutes, 0);
        // Convert client's TZ to UTC0
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date->format('H:i');
    }

}
