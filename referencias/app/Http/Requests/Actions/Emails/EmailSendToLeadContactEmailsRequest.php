<?php

namespace App\Http\Requests\Actions\Emails;

use App\Models\LeadContactEmail;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;


class EmailSendToLeadContactEmailsRequest extends APIBaseRequest
{

    protected $leadContactEmails = [];


    public function rules()
    {
        return [
            'body' => 'bail|required|string',
            'subject' => 'bail|required|string',
            'lead_contact_email' => ['bail', 'required', new IsRequiredIntegerOrArray()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $lead = request()->lead;
                $client = request()->client;

                if (!is_array(request()->input('lead_contact_email'))) {
                    $leadContactEmailIds = [request()->input('lead_contact_email')];
                } else {
                    $leadContactEmailIds = request()->input('lead_contact_email');
                }

                $this->leadContactEmails = LeadContactEmail::whereIn('id', $leadContactEmailIds)->get();
                if ($this->leadContactEmails->isEmpty()) {
                    $validator->errors()->add('lead_contact_email', 'lead_contact_emails_do_not_exists');
                    return false;
                }
                foreach ($this->leadContactEmails as $leadContactEmail) {
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
                }
            }
        });
    }


    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        unset($validated['lead_contact_email']);
        return $validated;
    }


    public function leadContactEmails()
    {
        return $this->leadContactEmails;
    }

}
