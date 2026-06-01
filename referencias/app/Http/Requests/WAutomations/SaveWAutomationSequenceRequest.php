<?php

namespace App\Http\Requests\WAutomations;

use App\Models\Tag;
use App\Models\Status;
use App\Rules\IsArrayOfIntegers;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InWAutomationSequenceReturnFields;
use App\DTO\WAutomations\WAutomationSequenceDTO;


class SaveWAutomationSequenceRequest extends APIBaseRequest
{

    private $client;
    private $triggeringTags;
    private $cancellingTags;
    private $triggeringStatus;
    private $cancellingStatus;


    public function rules()
    {
        $opts = ['canBeEmpty' => true];
        return [
            'enabled' => ['required', 'boolean'],
            'do_not_send_weekends' => ['sometimes', 'boolean'],
            'cancel_if_sequence_was_sent' => ['sometimes', 'boolean'],
            'trigger_type' => ['required', 'string', 'in:after_sale,after_sent_proposal'],
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
                
                $triggeringTagIds = request()->input('triggering_tags_ids') ?? [];
                $cancellingTagIds = request()->input('cancelling_tags_ids') ?? [];
                $triggeringStatusIds = request()->input('triggering_status_ids') ?? [];
                $cancellingStatusIds = request()->input('cancelling_status_ids') ?? [];

                if ($triggeringTagIds) {
                    $triggeringTags = Tag::where('client_id', $clientId)->whereIn('id', $triggeringTagIds)->get();
                    if ($triggeringTags->count() != count($triggeringTagIds)) {
                        $validator->errors()->add('triggering_tags_ids', 'not_all_triggering_tags_exist');
                        return false;
                    }
                    $this->triggeringTags = $triggeringTags;
                }

                if ($triggeringStatusIds) {
                    $triggeringStatus = Status::where('client_id', $clientId)
                        ->whereIn('id', $triggeringStatusIds)
                        ->get()
                    ;
                    if ($triggeringStatus->count() != count($triggeringStatusIds)) {
                        $validator->errors()->add('triggering_status_ids', 'not_all_triggering_status_exist');
                        return false;
                    }
                    $this->triggeringStatus = $triggeringStatus;
                }

                if ($cancellingTagIds) {
                    $cancellingTags = Tag::where('client_id', $clientId)->whereIn('id', $cancellingTagIds)->get();
                    if ($cancellingTags->count() != count($cancellingTagIds)) {
                        $validator->errors()->add('cancelling_tags_ids', 'not_all_cancelling_tags_exist');
                        return false;
                    }
                    $this->cancellingTags = $cancellingTags;
                }

                if ($cancellingStatusIds) {
                    $cancellingStatus = Status::where('client_id', $clientId)
                        ->whereIn('id', $cancellingStatusIds)
                        ->get()
                    ;
                    if ($cancellingStatus->count() != count($cancellingStatusIds)) {
                        $validator->errors()->add('cancelling_status_ids', 'not_all_cancelling_status_exist');
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
