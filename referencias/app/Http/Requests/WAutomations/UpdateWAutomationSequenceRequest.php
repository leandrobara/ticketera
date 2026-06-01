<?php

namespace App\Http\Requests\WAutomations;

use App\Models\Tag;
use App\Models\Status;
use App\Rules\IsArrayOfIntegers;
use App\Models\WAutomationSequence;
use App\Http\Requests\APIBaseRequest;
use App\DTO\WAutomations\WAutomationSequenceDTO;
use App\Rules\InWAutomationSequenceReturnFields;


class UpdateWAutomationSequenceRequest extends APIBaseRequest
{

    private $triggeringTags;
    private $triggeringStatus;


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
            'fields.*' => ['sometimes', new InWAutomationSequenceReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $clientId = request()->input('client')->id;
                $triggeringTagIds = request()->input('triggering_tags_ids');
                $triggeringStatusIds = request()->input('triggering_status_ids');

                if ($triggeringStatusIds && $triggeringTagIds) {
                    $validator->errors()->add(
                        'triggering_status_ids', 'cannot_use_triggering_tags_and_status_at_the_same_time'
                    );
                    return false;
                }

                $wAutomationSequence = request()->wAutomationSequence;
                $where = ['client_id' => $clientId, 'trigger_type' => 'after_tag_status_change'];
                $wAutomations = WAutomationSequence::where($where)->where('id', '!=', $wAutomationSequence->id)->get();

                if ($triggeringTagIds) {
                    $tags = Tag::where('client_id', $clientId)->whereIn('id', $triggeringTagIds)->get();
                    if ($tags->count() != count($triggeringTagIds)) {
                        $validator->errors()->add('triggering_tags_ids', 'not_all_triggering_tags_exists');
                        return false;
                    }
                    $tagsAreBeingUsed = $wAutomations->pluck('triggering_tags_ids')
                        ->flatten()
                        ->filter(null)
                        ->intersect($triggeringTagIds)
                        ->isNotEmpty()
                    ;
                    if ($tagsAreBeingUsed) {
                        $validator->errors()->add(
                            'triggering_tags_ids', 'triggering_tags_ids_are_being_used_by_another_wautomation'
                        );
                        return false;
                    }
                    $this->triggeringTags =  $tags;
                }

                if ($triggeringStatusIds) {
                    $triggeringStatus = Status::where('client_id', $clientId)
                        ->whereIn('id', $triggeringStatusIds)
                        ->get()
                    ;
                    if ($triggeringStatus->count() != count($triggeringStatusIds)) {
                        $validator->errors()->add('triggering_status_ids', 'not_all_triggering_status_exists');
                        return false;
                    }
                    $statusIsBeingUsed = $wAutomations->pluck('triggering_status_ids')
                        ->flatten()
                        ->filter(null)
                        ->intersect($triggeringStatusIds)
                        ->isNotEmpty()
                    ;
                    if ($statusIsBeingUsed) {
                        $validator->errors()->add(
                            'triggering_status_ids', 'triggering_status_ids_are_being_used_by_another_wautomation'
                        );
                        return false;
                    }
                    $this->triggeringStatus = $triggeringStatus;
                }

                $cancellingTagIds = request()->input('cancelling_tags_ids');
                if ($cancellingTagIds) {
                    $tags = Tag::where('client_id', $clientId)->whereIn('id', $cancellingTagIds)->get();
                    if ($tags->count() != count($cancellingTagIds)) {
                        $validator->errors()->add('cancelling_tags_ids', 'not_all_cancelling_tags_exists');
                        return false;
                    }
                    $this->cancellingTags =  $tags;
                }

                $cancellingStatusIds = request()->input('cancelling_status_ids');
                if ($cancellingStatusIds) {
                    $cancellingStatus = Status::where('client_id', $clientId)->whereIn(
                        'id', $cancellingStatusIds
                    )->get();
                    if ($cancellingStatus->count() != count($cancellingStatusIds)) {
                        $validator->errors()->add('cancelling_status_ids', 'not_all_cancelling_status_exists');
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
        $validated['cancellingTags'] = $this->cancellingTags;
        $validated['triggeringStatus'] = $this->triggeringStatus;
        $validated['cancellingStatus'] = $this->cancellingStatus;
        return WAutomationSequenceDTO::build($validated);
    }

}
