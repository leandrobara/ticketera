<?php

namespace App\Http\Requests\Notifications;

use App\Models\User;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InEmailTemplateReturnFields;


class PhoneCallButtonClickedNotificationRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'phoneNumber' => ['required', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $lead = request()->lead;

                if ($lead->client_id != $client->id) {
                    $validator->errors()->add('client_id', 'lead_does_not_belong_to_client');
                    return false;
                }
            }
        });
    }


    public function getPhoneNumber()
    {
        return request()->input('phoneNumber');
    }

}
