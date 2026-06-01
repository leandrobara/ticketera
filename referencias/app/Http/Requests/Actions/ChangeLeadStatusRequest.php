<?php

namespace App\Http\Requests\Actions;

use App\Http\Requests\APIBaseRequest;


class ChangeLeadStatusRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->lead->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'Lead client does not match with authenticated client');
            }
            if (request()->status->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'Status client does not match with authenticated client');
            }
        });
    }

}
