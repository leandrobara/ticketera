<?php

namespace App\Http\Requests\Automations;

use App\Http\Requests\APIBaseRequest;
use App\Rules\InAutomationEmailSendReturnFields;
use App\DTO\Automations\AutomationEmailSendDTO;
use App\Models\AutomationEmailSend;
use App\Models\Status;
use App\Models\Tag;
use App\Rules\IsArrayOfIntegers;

class UpdateAutomationEmailSendRequest extends APIBaseRequest
{
    private $triggeringStatus = null;

    private $triggeringTags = null;

    public function rules()
    {
        $opts = ['canBeEmpty' => true];

        return [
            'name' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
            'do_not_send_weekends' => ['sometimes', 'boolean'],
            'cancel_if_sequence_was_sent' => ['sometimes', 'boolean'],
            'trigger_type' => ['required', 'string', 'in:after_tag_status_change'],
            'triggering_tags_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'cancelling_tags_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'triggering_status_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'cancelling_status_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InAutomationEmailSendReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');

                $triggeringTagIds = request()->input('triggering_tags_ids');
                $triggeringStatusIds = request()->input('triggering_status_ids');

                if ($triggeringStatusIds && $triggeringTagIds) {
                    $validator->errors()->add(
                        'triggering_status_ids', 'cannot_use_triggering_tags_and_status_at_the_same_time'
                    );
                    return false;
                }

                // get all automation by client
                $automationEmailSend = request()->automationEmailSend;
                $where = ['client_id' => $client->id, 'trigger_type' => 'after_tag_status_change'];
                $automations = AutomationEmailSend::where($where)->where('id', '!=', $automationEmailSend->id)->get();

                if ($triggeringTagIds) {
                    $tags = Tag::where('client_id', $client->id)->whereIn('id', $triggeringTagIds)->get();
                    if ($tags->count() != count($triggeringTagIds)) {
                        $validator->errors()->add('triggering_tags_ids', 'not_all_triggering_tags_exists');
                        return false;
                    }

                    $tagsAreBeingUsed = $automations->pluck('triggering_tags_ids')
                        ->flatten()
                        ->filter(null)
                        ->intersect($triggeringTagIds)
                        ->isNotEmpty()
                    ;
                    if ($tagsAreBeingUsed) {
                        $validator->errors()->add(
                            'triggering_tags_ids', 'triggering_tags_ids_are_being_used_by_another_automation'
                        );
                        return false;
                    }
                    $this->triggeringTags =  $tags;
                }

                if ($triggeringStatusIds) {
                    $triggeringStatus = Status::where('client_id', $client->id)->whereIn(
                        'id', $triggeringStatusIds
                    )->get();
                    if ($triggeringStatus->count() != count($triggeringStatusIds)) {
                        $validator->errors()->add(
                            'triggering_status_ids', 'not_all_triggering_status_exists'
                        );
                        return false;
                    }

                    $statusAreBeingUsed = $automations->pluck('triggering_status_ids')
                        ->flatten()
                        ->filter(null)
                        ->intersect($triggeringStatusIds)
                        ->isNotEmpty()
                    ;
                    if ($statusAreBeingUsed) {
                        $validator->errors()->add(
                            'triggering_status_ids', 'triggering_status_ids_are_being_used_by_another_automation'
                        );
                        return false;
                    }
                    $this->triggeringStatus = $triggeringStatus;
                }

                $cancellingTagIds = request()->input('cancelling_tags_ids');
                if ($cancellingTagIds) {
                    $tags = Tag::where('client_id', $client->id)->whereIn('id', $cancellingTagIds)->get();
                    if ($tags->count() != count($cancellingTagIds)) {
                        $validator->errors()->add('cancelling_tags_ids', 'not_all_cancelling_tags_exists');
                        return false;
                    }
                    $this->cancellingTags =  $tags;
                }

                $cancellingStatusIds = request()->input('cancelling_status_ids');
                if ($cancellingStatusIds) {
                    $cancellingStatus = Status::where('client_id', $client->id)->whereIn(
                        'id',
                        $cancellingStatusIds
                    )->get();
                    if ($cancellingStatus->count() != count($cancellingStatusIds)) {
                        $validator->errors()->add(
                            'cancelling_status_ids',
                            'not_all_cancelling_status_exists'
                        );
                        return false;
                    }
                    $this->cancellingStatus = $cancellingStatus;
                }
            }
        });
    }


    public function validatedDTO()
    {
        $validated = parent::validated();
        $validated['client'] = request()->input('client');
        $validated['triggeringTags'] = $this->triggeringTags;
        $validated['triggeringStatus'] = $this->triggeringStatus;
        $validated['cancellingTags'] = $this->cancellingTags;
        $validated['cancellingStatus'] = $this->cancellingStatus;

        return AutomationEmailSendDTO::build($validated);
    }
}
