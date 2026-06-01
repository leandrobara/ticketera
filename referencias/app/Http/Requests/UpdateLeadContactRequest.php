<?php

namespace App\Http\Requests;

use App\Rules\InLeadContactReturnFields;

class UpdateLeadContactRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            "name" =>  ['sometimes', 'string'],
            "last_name" =>  ['sometimes', 'nullable', 'string'],
            "role" =>  ['sometimes', 'string'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', "string", new InLeadContactReturnFields() ]
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->leadContact->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'lead_contact_client_does_not_match_with_authenticated_client');
            }
        });
    }

    public function validatedAttributes()
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }
}
