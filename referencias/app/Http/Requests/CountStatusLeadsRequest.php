<?php

namespace App\Http\Requests;

class CountStatusLeadsRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->status->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id',
                    'status_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }
}
