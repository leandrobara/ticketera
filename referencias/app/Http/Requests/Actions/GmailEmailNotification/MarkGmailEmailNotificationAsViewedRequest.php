<?php

namespace App\Http\Requests\Actions\GmailEmailNotification;

use App\Http\Requests\APIBaseRequest;


class MarkGmailEmailNotificationAsViewedRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->gmailEmailNotification->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id',
                    'GmailEmailNotification client does not match with authenticated client'
                );
                return false;
            }
        });
    }

}
