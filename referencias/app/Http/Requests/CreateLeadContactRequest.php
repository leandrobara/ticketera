<?php

namespace App\Http\Requests;

use App\Rules\InLeadContactReturnFields;

class CreateLeadContactRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'name' =>  ['required', 'string'],
            'role' =>  ['sometimes', 'string'],
            'is_main' => ['sometimes', 'bool'],
            'last_name' =>  ['sometimes', 'string'],
            'email' =>  ['sometimes', 'email'],
            'phone' =>  ['sometimes', 'string'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', 'string', new InLeadContactReturnFields()]
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ((int) request()->lead->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'lead_client_does_not_match_with_authenticated_client');
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
