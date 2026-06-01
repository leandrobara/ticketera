<?php

namespace App\Http\Requests;

use App\Repositories\LeadContactEmailRepository;

class UpdateLeadContactEmailRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'email' => ['required', 'email']
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                if (request()->leadContactEmail->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id', 'lead_contact_email_client_does_not_match_with_authenticated_client'
                    );
                }
                $existentLeadContactEmail = resolve(LeadContactEmailRepository::class)->findOneByLeadAndEmail(
                    request()->leadContactEmail->lead,
                    request()->input('email')
                );

                if ($existentLeadContactEmail && $existentLeadContactEmail->id != request()->leadContactEmail->id) {
                    $validator->errors()->add('email', 'lead_contact_email_already_exists');
                }
            }
        });
    }
}
