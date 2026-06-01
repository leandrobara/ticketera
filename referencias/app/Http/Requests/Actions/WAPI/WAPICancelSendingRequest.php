<?php

namespace App\Http\Requests\Actions\WAPI;

use DateTime;
use DateTimeZone;
use App\Models\WhatsAppAttachment;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadContactPhoneService;
use App\DTO\WAPI\WAPINewSendingParametersDTO;


class WAPICancelSendingRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->client;
                $whatsAppSending = request()->whatsAppSending;

                if ($whatsAppSending->client_id != $client->id) {
                    $validator->errors()->add('whatsapp_sending', 'whatsapp_sending_does_not_belong_to_client');
                    return false;
                }
                if ($whatsAppSending->finished_date) {
                    $validator->errors()->add('whatsapp_sending', 'whatsapp_sending_was_already_finished');
                    return false;
                }
                if ($whatsAppSending->finished_date) {
                    $validator->errors()->add('whatsapp_sending', 'whatsapp_sending_was_already_finished');
                    return false;
                }
                if ($whatsAppSending->failed_date) {
                    $validator->errors()->add('whatsapp_sending', 'whatsapp_sending_have_already_failed');
                    return false;
                }
                if ($whatsAppSending->is_automation) {
                    $validator->errors()->add('whatsapp_sending', 'whatsapp_sending_automation_can_not_be_cancelled');
                    return false;
                }
                if (!$whatsAppSending->isWapiType()) {
                    $validator->errors()->add('whatsapp_sending', 'whatsapp_sending_is_not_wapi_type');
                    return false;
                }
            }
        });
    }

}
