<?php

namespace App\Http\Requests\FacebookPage;

use App\Http\Requests\APIBaseRequest;

class ValidatedFacebookPageWebhookRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'hub_mode' => ['required', 'string'],
            'hub_challenge' => ['required', 'string'],
            'hub_verify_token' => ['required', 'string'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $val['hub_mode'] = request()->input('hub_mode');
        $val['hub_challenge'] = request()->input('hub_challenge');
        $val['hub_verify_token'] = request()->input('hub_verify_token');

        return $val;
    }
}
