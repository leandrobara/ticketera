<?php

namespace App\Http\Requests\Automations;

use App\Models\Status;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InAutomationEmailSendReturnFields;
use App\DTO\Automations\AutomationEmailSendDTO;
use App\Models\AutomationEmailSend;
use App\Models\Tag;
use App\Rules\IsArrayOfIntegers;

class CreateAutomationEmailSendRequest extends APIBaseRequest
{

    private $client = null;
    private $cancellingTags = null;
    private $triggeringTags = null;
    private $triggeringStatus = null;
    private $cancellingStatus = null;


    public function rules()
    {
        $opts = ['canBeEmpty' => true];

        return [
            'enabled' => ['required', 'boolean'],
            'name' => ['required', 'string'],
            'trigger_type' => ['required', 'string', 'in:after_tag_status_change'],
            'triggering_tags_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'triggering_status_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'cancelling_tags_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'cancelling_status_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'cancel_if_sequence_was_sent' => ['sometimes', 'boolean'],
            'do_not_send_weekends' => ['sometimes', 'boolean'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InAutomationEmailSendReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $this->client = request()->input('client');

                $triggeringTagIds = request()->input('triggering_tags_ids');
                $triggeringStatusIds = request()->input('triggering_status_ids');

                if ($triggeringStatusIds && $triggeringTagIds) {
                        $validator->errors()->add(
                            'triggering_status_ids',
                            'cannot_use_triggering_tags_and_status_at_the_same_time'
                        );
                        return false;
                }

                // get all automation by client
                $automations = AutomationEmailSend::where([
                    'client_id' => $this->client->id,
                    'trigger_type' => 'after_tag_status_change'
                ])->get();

                // Triggering Tags Id
                if ($triggeringTagIds) {
                    $tags = Tag::where('client_id', $this->client->id)->whereIn('id', $triggeringTagIds)->get();
                    if ($tags->count() != count($triggeringTagIds)) {
                        $validator->errors()->add('triggering_tags_ids', 'not_all_triggering_tags_exists');
                        return false;
                    }

                    if (
                        $automations->pluck('triggering_tags_ids')
                            ->flatten()
                            ->filter(null)
                            ->intersect($triggeringTagIds)
                            ->isNotEmpty()
                    ) {
                        $validator->errors()->add(
                            'triggering_tags_ids',
                            'triggering_tags_ids_are_being_used_by_another_automation'
                        );
                        return false;
                    }

                    $this->triggeringTags =  $tags;
                }
                // Triggering Status Id
                if ($triggeringStatusIds) {
                    $triggeringStatus = Status::where('client_id', $this->client->id)->whereIn(
                        'id',
                        $triggeringStatusIds
                    )->get();
                    if ($triggeringStatus->count() != count($triggeringStatusIds)) {
                        $validator->errors()->add(
                            'triggering_status_ids',
                            'not_all_triggering_status_exists'
                        );
                        return false;
                    }
                    // check for used triggering_status_ids in another automation
                    if (
                        $automations->pluck('triggering_status_ids')
                            ->flatten()
                            ->filter(null)
                            ->intersect($triggeringStatusIds)
                            ->isNotEmpty()
                    ) {
                        $validator->errors()->add(
                            'triggering_tags_ids',
                            'triggering_status_ids_are_being_used_by_another_automation'
                        );
                        return false;
                    }

                    $this->triggeringStatus = $triggeringStatus;
                }

                // Canceling Tags Ids
                $cancellingTagIds = request()->input('cancelling_tags_ids');
                if ($cancellingTagIds) {
                    $tags = Tag::where('client_id', $this->client->id)->whereIn('id', $cancellingTagIds)->get();
                    if ($tags->count() != count($cancellingTagIds)) {
                        $validator->errors()->add('cancelling_tags_ids', 'not_all_cancelling_tags_exists');

                        return false;
                    }
                    // check for used cancelling_tags_ids in another automations
                    // if (
                    //     $automations->pluck('cancelling_tags_ids')
                    //         ->flatten()
                    //         ->filter(null)
                    //         ->intersect($cancellingTagIds)
                    //         ->isNotEmpty()
                    // ) {
                    //     $validator->errors()->add(
                    //         'cancelling_tags_ids',
                    //         'cancelling_tags_ids_are_being_used_by_another_automation'
                    //     );
                    //     return false;
                    // }

                    $this->cancellingTags =  $tags;
                }

                // Cancelling Status Ids
                $cancellingStatusIds = request()->input('cancelling_status_ids');
                if ($cancellingStatusIds) {
                    $cancellingStatus = Status::where('client_id', $this->client->id)->whereIn(
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
                    // check for used cancelling_status_ids in another automation
                    // if (
                    //     $automations->pluck('cancelling_status_ids')
                    //         ->flatten()
                    //         ->filter(null)
                    //         ->intersect($cancellingStatusIds)
                    //         ->isNotEmpty()
                    // ) {
                    //     $validator->errors()->add(
                    //         'cancelling_tags_ids',
                    //         'cancelling_status_ids_are_being_used_by_another_automation'
                    //     );
                    //     return false;
                    // }
                    $this->cancellingStatus = $cancellingStatus;
                }
            }
        });
    }


    public function validatedDTO()
    {
        $validated = parent::validated();
        $validated['client'] = $this->client;
        $validated['triggeringTags'] = $this->triggeringTags;
        $validated['triggeringStatus'] = $this->triggeringStatus;
        $validated['cancellingTags'] = $this->cancellingTags;
        $validated['cancellingStatus'] = $this->cancellingStatus;
        return AutomationEmailSendDTO::build($validated);
    }

}
