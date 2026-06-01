<?php

namespace App\Http\Requests\Actions\Emails;

use App\Models\Attachment;
use App\DTO\EmailSendParametersDTO;
use App\Http\Requests\APIBaseRequest;


class EmailSendToLeadRequest extends APIBaseRequest
{

    protected $attachments = [];
    protected $leadContactEmails = [];


    public function rules()
    {
        return [
            'cc' => 'bail|sometimes|array',
            'cc.*' => 'bail|sometimes|email',
            'body' => 'bail|required|string',
            'subject' => 'bail|required|string',
            'isProposal' => 'bail|sometimes|boolean',
            'attachmentIds' => 'bail|sometimes|array',
            'attachmentIds.*' => 'bail|sometimes|integer',
            'leadContactEmailIds' => 'bail|sometimes|array',
            'leadContactEmailIds.*' => 'bail|sometimes|integer',
            // 'sendDate' => 'required|date_format:Y-m-d\TH:i:sP',
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $lead = request()->lead;
                $client = request()->client;
                $lead = request()->lead;

                if ($lead->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id', 'lead_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }

                $leadContactEmailIds = collect(request()->input('leadContactEmailIds'));
                if ($leadContactEmailIds->isEmpty()) {
                    $validator->errors()->add('lead_contact_email_id', 'lead_contact_emails_dont_exist');
                    return false;
                }

                $leadContactEmails = $lead
                    ->leadContactEmails
                    ->filter(function ($leadContactEmail) use ($leadContactEmailIds) {
                        return $leadContactEmailIds->contains($leadContactEmail->id);
                    })
                ;

                foreach ($leadContactEmails as $leadContactEmail) {
                    if ($leadContactEmail->lead_id != $lead->id) {
                        $validator->errors()->add('lead_id', 'lead_contact_email_lead_does_not_match');
                        return false;
                    }
                }
                if ($leadContactEmails->isEmpty()) {
                    $validator->errors()->add('lead_contact_email_id', 'lead_contact_emails_dont_exist');
                        return false;
                }
                $this->leadContactEmails = $leadContactEmails;


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
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        $validated['attachments'] = $this->attachments;
        $validated['leadContactEmails'] = $this->leadContactEmails;
        return $validated;
    }


    public function validatedDTO(): EmailSendParametersDTO
    {
        $dto = EmailSendParametersDTO::buildFromRequestArray($this->validated());
        return $dto;
    }

}
