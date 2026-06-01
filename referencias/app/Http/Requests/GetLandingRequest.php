<?php

namespace App\Http\Requests;

use App\Rules\InLandingReturnFields;

class GetLandingRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InLandingReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->landing->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'landing_client_does_not_match_with_authenticated_client');
            }
        });
    }
}
