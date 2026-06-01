<?php

namespace App\Http\Requests\WAutomations;

use App\Http\Requests\APIBaseRequest;
use App\Rules\InWAutomationSequenceReturnFields;


class DeleteWAutomationSequenceRequest extends APIBaseRequest
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
                $wAutomationSequence = request()->wAutomationSequence;
                if ($wAutomationSequence->client_id != $clientId) {
                    $validator->errors()->add(
                        'client_id', 'wautomation_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }
            }
        });
    }

}
