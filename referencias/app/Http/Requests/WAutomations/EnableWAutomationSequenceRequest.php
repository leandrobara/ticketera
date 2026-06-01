<?php

namespace App\Http\Requests\WAutomations;

use App\Models\Tag;
use App\Models\Status;
use App\Rules\IsArrayOfIntegers;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InWAutomationSequenceReturnFields;
use App\DTO\WAutomations\WAutomationSequenceDTO;


class EnableWAutomationSequenceRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InWAutomationSequenceReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $clientId = request()->input('client')->id;
                $wAutomation = request()->wAutomationSequence;
                if ($wAutomation->client_id != $clientId) {
                    $validator->errors()->add(
                        'client_id', 'automation_client_does_not_match_with_authenticated_client'
                    );
                }
            }
        });
    }

}
