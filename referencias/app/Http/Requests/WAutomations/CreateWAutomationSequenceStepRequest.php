<?php

namespace App\Http\Requests\WAutomations;

use DateTime;
use DateTimeZone;
use App\Models\Tag;
use App\Models\Status;
use App\Rules\IsArrayOfIntegers;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\Models\WAutomationSequenceStep;
use App\Rules\InWAutomationSequenceStepReturnFields;
use App\DTO\WAutomations\WAutomationSequenceStepDTO;


class CreateWAutomationSequenceStepRequest extends APIBaseRequest
{

    private $tagsToAdd;
    private $statusToAdd;
    private $sendWhatsAppTemplate;


    public function rules()
    {
        return [
            'send_delay_days' => ['required', 'integer'],
            'send_whatsapp_template_id' => ['required', 'integer'],
            'add_status_id' => ['sometimes', 'integer', 'nullable'],
            'send_hour' => ['sometimes', 'nullable', 'date_format:H:i'],
            'send_delay_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:120'],
            'add_tags_ids' => ['sometimes', 'array', new IsArrayOfIntegers(['canBeEmpty' => true])],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InWAutomationSequenceStepReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $sendHour = request()->get('send_hour');
                $clientId = request()->input('client')->id;
                $addTagIds = request()->input('add_tags_ids') ?? [];
                $wAutomationSequence = request()->wAutomationSequence;
                $sendDelayDays = (int) request()->get('send_delay_days');
                $addStatusId = request()->input('add_status_id') ?? null;
                $sendDelayMinutes = (int) request()->get('send_delay_minutes');
                $whatsAppTemplateId = request()->input('send_whatsapp_template_id');

                if ($wAutomationSequence->client_id != $clientId) {
                    $validator->errors()->add(
                        'client_id', 'wautomation_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }

                if (!$sendDelayDays && !$sendDelayMinutes) {
                    $validator->errors()->add('send_delay_days', 'send_delay_minutes_can_not_be_null');
                    return false;
                }
                if ($sendDelayDays && $sendDelayMinutes) {
                    $validator->errors()->add('send_delay_days', 'invalid_days_hour_minutes_combination');
                    return false;
                }

                $conditions = $this->buildWAutomationSequenceStepConditions();
                $existentStep = WAutomationSequenceStep::where($conditions)->first();
                if ($existentStep) {
                    $validator->errors()->add('send_delay_days', 'step_with_that_conditions_already_created');
                    return false;
                }

                $this->sendWhatsAppTemplate = WhatsAppTemplate::where('client_id', $clientId)
                    ->where('id', $whatsAppTemplateId)
                    ->first()
                ;
                if (!$this->sendWhatsAppTemplate) {
                    $validator->errors()->add('send_whatsapp_template_id', 'whatsapp_template_does_not_exist');
                    return false;
                }

                if ($addTagIds) {
                    $tagsToAdd = Tag::where('client_id', $clientId)->whereIn('id', $addTagIds)->get();
                    if ($tagsToAdd->count() != count($addTagIds)) {
                        $validator->errors()->add('add_tags_ids', 'not_all_tags_exist');
                        return false;
                    }
                    $tagsAreBeingUsed = collect($wAutomationSequence->triggering_tags_ids)
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


    public function validatedDTO(): WAutomationSequenceStepDTO
    {
        $val = parent::validated();

        $val['client'] = request()->input('client');
        $val['statusToAdd'] = $this->statusToAdd ?? null;
        $val['tagsToAdd'] = $this->tagsToAdd ?? new Collection();
        $val['sendWhatsAppTemplate'] = $this->sendWhatsAppTemplate;
        $val['wAutomationSequence'] = request()->wAutomationSequence;
        $val['send_hour'] = $val['send_hour'] ? $this->getFormattedSendHourByTimeZone($val['send_hour']) : null;
        
        $dto = WAutomationSequenceStepDTO::build($val);
        return $dto;
    }


    private function buildWAutomationSequenceStepConditions(): array
    {
        $sendHour = request()->get('send_hour');
        $sendDelayDays = (int) request()->get('send_delay_days');
        $sendDelayMinutes = (int) request()->get('send_delay_minutes');

        $conditions = [
            'client_id' => request()->input('client')->id,
            'send_delay_days' => request()->get('send_delay_days'),
            'wautomation_sequence_id' => request()->wAutomationSequence->id,
        ];
        if ($sendDelayDays) {
            // send_hour puede ser NULL (horario original del evento trigger)
            $conditions['send_hour'] = $sendHour ? $this->getFormattedSendHourByTimeZone($sendHour) : null;
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
