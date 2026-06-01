<?php

namespace App\Http\Requests;

use App\Repositories\LeadContactPhoneRepository;

class CreateLeadContactPhoneRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'phone' => ['required', 'string']
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                if (request()->leadContact->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id',
                        'lead_contact_phone_client_does_not_match_with_authenticated_client'
                    );

                    return false;
                }
                $existentLeadContactPhone = resolve(LeadContactPhoneRepository::class)->findOneByLeadAndPhone(
                    request()->leadContact->lead,
                    request()->input('phone')
                );
                if ($existentLeadContactPhone) {
                    $validator->errors()->add('phone', 'lead_contact_phone_already_exists');
                }
            }
        });
    }
}
