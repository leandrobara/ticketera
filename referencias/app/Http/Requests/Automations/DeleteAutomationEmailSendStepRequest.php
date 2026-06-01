<?php

namespace App\Http\Requests\Automations;

use App\Http\Requests\APIBaseRequest;

class DeleteAutomationEmailSendStepRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $automationEmailSend = request()->automationEmailSend;
                $automationEmailSendStep = request()->automationEmailSendStep;

                if ($automationEmailSend->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id',
                        'automation_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }
                if ($automationEmailSendStep->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id',
                        'automation_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }
                if ($automationEmailSendStep->automation_email_send_id != $automationEmailSend->id) {
                    $validator->errors()->add(
                        'automation_id',
                        'automation_step_does_not_belong_to_automation_email'
                    );
                    return false;
                }
            }
        });
    }
}
