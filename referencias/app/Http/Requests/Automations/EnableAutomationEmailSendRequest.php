<?php

namespace App\Http\Requests\Automations;

use App\Models\Tag;
use App\Models\Status;
use App\Rules\IsArrayOfIntegers;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InAutomationEmailSendReturnFields;
use App\DTO\Automations\AutomationEmailSendDTO;


class EnableAutomationEmailSendRequest extends APIBaseRequest
{
    private $triggeringStatus = null;

    private $triggeringTags = null;


    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InAutomationEmailSendReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $automation = request()->automationEmailSend;

                if ($automation->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id',
                        'automation_client_does_not_match_with_authenticated_client'
                    );
                }
            }
        });
    }

}
