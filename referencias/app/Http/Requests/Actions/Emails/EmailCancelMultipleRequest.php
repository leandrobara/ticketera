<?php

namespace App\Http\Requests\Actions\Emails;

use DateTime;
use DateTimeZone;
use App\Models\Attachment;
use App\Services\API\EmailService;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;
use App\Services\API\LeadContactEmailService;
use App\DTO\EmailMassiveScheduleParametersDTO;


class EmailCancelMultipleRequest extends APIBaseRequest
{

    private $emails = null;


    public function rules()
    {
        return [
            'id' => ['required', new IsRequiredIntegerOrArray()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $client = request()->input('client');
            $emailIds = request()->input('id');
            if (!is_array($emailIds)) {
                $emailIds = [$emailIds];
            }

            $emails = resolve(EmailService::class)->findByIdsAndClient($emailIds, $client);
            if ($emails->isEmpty()) {
                $validator->errors()->add('email_id', 'Emails do not exist');
                return false;
            }

            if (count($emailIds) != $emails->count()) {
                $validator->errors()->add('app_id', 'Email app does not match with authe authenticated client');
                return false;
            }

            foreach ($emails as $email) {
                if ($email->client_id != $client->id) {
                    $validator->errors()->add('app_id', 'Email app does not match with authenticated client');
                    return false;
                }
                if ($email->sent_date) {
                    $validator->errors()->add('email_sent', 'Email has being already sent');
                    return false;
                }
                if ($email->cancelled_date) {
                    $validator->errors()->add('email_cancelled', 'Email has being already cancelled');
                    return false;
                }
            }

            $this->emails = $emails;
        });
    }


    public function getEmailsToCancel()
    {
        return $this->emails;
    }

}
