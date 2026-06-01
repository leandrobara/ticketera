<?php

namespace App\Http\Requests\Actions\Emails;

use App\Models\Attachment;
use App\DTO\EmailSendParametersDTO;
use App\Http\Requests\APIBaseRequest;


class EmailSendToLeadContactEmailRequest extends APIBaseRequest
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
                        'client_id', 'contact_email_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }

                if ($leadContactEmail->lead_id != $lead->id) {
                    $validator->errors()->add('lead_id', 'contact_email_lead_does_not_match');
                    return false;
                }

                if ($client->clientSettings->email_sending_blocked) {
                    $validator->errors()->add('client_id', 'email_sending_blocked');
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
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        $validated['attachments'] = $this->attachments;
        return $validated;
    }


    public function validatedDTO(): EmailSendParametersDTO
    {
        $dto = EmailSendParametersDTO::buildFromRequestArray($this->validated());
        return $dto;
    }
}
