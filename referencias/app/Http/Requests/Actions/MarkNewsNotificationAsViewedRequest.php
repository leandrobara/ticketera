<?php

namespace App\Http\Requests\Actions;

use App\Http\Requests\APIBaseRequest;


class MarkNewsNotificationAsViewedRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $loginUserId = request()->user->id;
                $loginClientId = request()->client->id;
                $newsNotification = request()->newsNotification;
                
                if ($loginUserId != $newsNotification->user_id) {
                    $validator->errors()->add('user_id', 'user_does_not_match_with_authenticated_user');
                    return false;
                }
                if ($loginClientId != $newsNotification->client_id) {
                    $validator->errors()->add('client_id', 'client_does_not_match_with_authenticated_client');
                    return false;
                }
            });
        }
    }

}
