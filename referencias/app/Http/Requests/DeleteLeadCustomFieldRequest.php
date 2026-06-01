<?php

namespace App\Http\Requests;

use App\Rules\InLeadCustomFieldReturnFields;


class DeleteLeadCustomFieldRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InLeadCustomFieldReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->leadCustomField->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id', 'lead_custom_field_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }

}
