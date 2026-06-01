<?php

namespace App\Http\Requests\Views\WAPSender;

use DateTime;
use App\Models\Lead;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class WAPSenderGetSendingQuotaByDateRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'sendDate' => ['required', 'date_format:Y-m-d'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');
                $loginUser = request()->input('user');

                if (!$loginUser->wap_sender_session_phone_number) {
                    $validator->errors()->add('wap_sender', 'wap_sender_is_not_enabled_for_user');
                    return false;
                }
                if (!$client->clientSettings->enable_whatsapp_sender_job_sending) {
                    $validator->errors()->add('wap_sender', 'wap_sender_is_not_enabled_for_client');
                    return false;
                }
            });
        }
    }


    public function getSendDate(): DateTime
    {
        $val = parent::validated();
        return new DateTime($val['sendDate']);
    }

}
