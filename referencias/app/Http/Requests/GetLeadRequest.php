<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Rules\InLeadReturnFields;


class GetLeadRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', 'string', new InLeadReturnFields()]
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->lead->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'lead_client_does_not_match_with_authenticated_client');
            }
        });
    }

}
