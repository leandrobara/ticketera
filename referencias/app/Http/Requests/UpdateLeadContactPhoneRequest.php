<?php

namespace App\Http\Requests;

use App\Repositories\LeadContactPhoneRepository;

class UpdateLeadContactPhoneRequest extends APIBaseRequest
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
            $client = request()->input('client');
            if (request()->leadContactPhone->client_id != $client->id) {
                $validator->errors()->add(
                    'client_id',
                    'lead_contact_phone_client_does_not_match_with_authenticated_client'
                );
            }
            $existentLeadContactPhone = resolve(LeadContactPhoneRepository::class)->findOneByLeadAndPhone(
                request()->leadContactPhone->lead,
                request()->input('phone')
            );
            if ($existentLeadContactPhone && $existentLeadContactPhone->id != request()->leadContactPhone->id) {
                $validator->errors()->add('phone', 'lead_contact_phone_already_exists');
            }
        });
    }
}
