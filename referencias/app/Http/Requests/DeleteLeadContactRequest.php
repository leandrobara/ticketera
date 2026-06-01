<?php

namespace App\Http\Requests;

class DeleteLeadContactRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $lead = request()->leadContact->lead;
            if (request()->leadContact->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'lead_contact_client_does_not_match_with_authenticated_client');

                return false;
            }
            if ($lead->leadContacts->count() <= 1) {
                $validator->errors()->add('one_contact_left', 'lead_contacts_cannot_be_empty');

                return false;
            }
        });
    }
}
