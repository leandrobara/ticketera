<?php

namespace App\Http\Requests\Actions\Emails;

use DateTime;
use DateTimeZone;
use App\Models\Email;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadContactEmailService;
use App\DTO\EmailMassiveScheduleParametersDTO;


class EmailCancelMassiveRequest extends APIBaseRequest
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
                $externalMassiveId = request()->externalMassiveId;
                $email = Email::where('external_massive_id', $externalMassiveId)
                    ->where('client_id', $client->id)
                    ->take(1)
                    ->first()
                ;
                if (!$email) {
                    $validator->errors()->add('external_massive_id', 'email_does_not_exist');
                    return false;
                }
                if ($email->client_id != $client->id) {
                    $validator->errors()->add('client_id', 'email_does_not_belong_to_client');
                    return false;
                }
            }
        });
    }

    public function getExternalMassiveId()
    {
        return request()->externalMassiveId;
    }

}
