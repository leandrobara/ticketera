<?php

namespace App\Http\Requests;


class DeleteLeadSaleRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->lead->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'client_does_not_match_with_authenticated_client');
            }
        });
    }

}
