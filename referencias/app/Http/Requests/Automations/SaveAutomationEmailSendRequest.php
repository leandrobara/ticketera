<?php

namespace App\Http\Requests\Automations;

use App\Models\Tag;
use App\Models\Status;
use App\Rules\IsArrayOfIntegers;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InAutomationEmailSendReturnFields;
use App\DTO\Automations\AutomationEmailSendDTO;


class SaveAutomationEmailSendRequest extends APIBaseRequest
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
            'trigger_type' => ['required', 'string', 'in:after_sale,after_sent_proposal'],
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
                $clientId = request()->input('client')->id;
                
                $triggeringTagIds = request()->input('triggering_tags_ids');
                if ($triggeringTagIds) {
                    $tags = Tag::where('client_id', $clientId)->whereIn('id', $triggeringTagIds)->get();
                    if ($tags->count() != count($triggeringTagIds)) {
                        $validator->errors()->add('triggering_tags_ids', 'not_all_triggering_tags_exists');

                        return false;
                    }
                    $this->triggeringTags =  $tags;
                }

                $triggeringStatusIds = request()->input('triggering_status_ids');
                if ($triggeringStatusIds) {
                    $triggeringStatus = Status::where('client_id', $clientId)
                        ->whereIn('id', $triggeringStatusIds)
                        ->get()
                    ;
                    if ($triggeringStatus->count() != count($triggeringStatusIds)) {
                        $validator->errors()->add('triggering_status_ids', 'not_all_triggering_status_exists');
                        return false;
                    }
                    $this->triggeringStatus = $triggeringStatus;
                }

                $cancellingTagIds = request()->input('cancelling_tags_ids');
                if ($cancellingTagIds) {
                    $cancellingTags = Tag::where('client_id', $clientId)->whereIn('id', $cancellingTagIds)->get();
                    if ($cancellingTags->count() != count($cancellingTagIds)) {
                        $validator->errors()->add('cancelling_tags_ids', 'not_all_cancelling_tags_exists');
                        return false;
                    }
                    $this->cancellingTags =  $cancellingTags;
                }

                $cancellingStatusIds = request()->input('cancelling_status_ids');
                if ($cancellingStatusIds) {
                    $cancellingStatus = Status::where('client_id', $clientId)
                        ->whereIn('id', $cancellingStatusIds)
                        ->get()
                    ;
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
        return AutomationEmailSendDTO::build($validated);
    }
}
