<?php

namespace App\Http\Requests\WAutomations;

use App\Http\Requests\APIBaseRequest;


class DeleteWAutomationSequenceStepRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $clientId = request()->input('client')->id;
                $wAutomationSequence = request()->wAutomationSequence;
                $wAutomationSequenceStep = request()->wAutomationSequenceStep;

                if ($wAutomationSequence->client_id != $clientId) {
                    $validator->errors()->add(
                        'client_id', 'wautomation_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }
                if ($wAutomationSequenceStep->client_id != $clientId) {
                    $validator->errors()->add(
                        'client_id', 'wautomation_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }
                if ($wAutomationSequenceStep->wautomation_sequence_id != $wAutomationSequence->id) {
                    $validator->errors()->add(
                        'wautomation_id', 'wautomation_step_does_not_belong_to_wautomation_sequence'
                    );
                    return false;
                }
            }
        });
    }
}
