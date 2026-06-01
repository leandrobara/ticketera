<?php

namespace App\Http\Requests\Automations;

use App\Http\Requests\APIBaseRequest;
use App\Rules\InAutomationEmailSendReturnFields;

class DeleteAutomationEmailSendRequest extends APIBaseRequest
{
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
                $automationEmailSend = request()->automationEmailSend;
                if ($automationEmailSend->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id',
                        'automation_client_does_not_match_with_authenticated_client'
                    );

                    return false;
                }
            }
        });
    }
}
