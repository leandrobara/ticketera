<?php

namespace App\Http\Requests\Actions\Emails;

use DateTime;
use DateTimeZone;
use App\Models\Attachment;
use App\DTO\EmailScheduleParametersDTO;
use App\Http\Requests\APIBaseRequest;


class EmailScheduleToLeadContactEmailRequest extends APIBaseRequest
{

    protected $attachments = [];
    protected $leadContactEmails = [];


    public function rules()
    {
        return [
            'body' => 'bail|required|string',
            'subject' => 'bail|required|string',
            'isProposal' => 'bail|sometimes|boolean',
            'attachmentIds' => 'bail|sometimes|array',
            'attachmentIds.*' => 'bail|sometimes|integer',
            'sendDate' => 'required|date_format:Y-m-d\TH:i:sP',
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $lead = request()->lead;
                $client = request()->client;
                $leadContactEmail = request()->leadContactEmail;

                if ($leadContactEmail->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id',
                        'contact_email_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }

                if ($client->clientSettings->email_sending_blocked) {
                    $validator->errors()->add('client_id', 'email_sending_blocked');
                    return false;
                }

                if ($leadContactEmail->lead_id != $lead->id) {
                    $validator->errors()->add('lead_id', 'contact_email_lead_does_not_match');
                    return false;
                }

                $attachmentIds = collect(request()->input('attachmentIds', []));
                $attachments = Attachment::where('client_id', $client->id)->whereIn('id', $attachmentIds)->get();
                if ($attachmentIds->count() != $attachments->count()) {
                    $validator->errors()->add('attachmentIds', 'some_attachments_does_not_belong_to_client');
                    return false;
                }
                $this->attachments = $attachments;
            }
        });
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        if ($val['fields'] ?? false) {
            unset($val['fields']);
        }
        $val['attachments'] = $this->attachments;
        
        $date = (new DateTime($val['sendDate']))->setTimezone(new DateTimeZone('UTC'));
        $val['sendDate'] = $date->format('Y-m-d\TH:i:sP');

        return $val;
    }


    public function validatedDTO(): EmailScheduleParametersDTO
    {
        $dto = EmailScheduleParametersDTO::buildFromRequestArray($this->validated());
        return $dto;
    }

}
