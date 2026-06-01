<?php

namespace App\Http\Requests\Automations;

use App\Http\Requests\APIBaseRequest;
use App\Rules\InAutomationTaskReturnFields;

class ModalAutomationTaskRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            //
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');

                if (request()->automationTask->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id',
                        'automation_client_does_not_match_with_authenticated_client'
                    );
                }
            }
        });
    }
}
