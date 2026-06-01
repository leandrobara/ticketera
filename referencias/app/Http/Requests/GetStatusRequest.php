<?php

namespace App\Http\Requests;

use App\Rules\InStatusReturnFields;

class GetStatusRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InStatusReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->status->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id',
                    'status_template_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }
}
