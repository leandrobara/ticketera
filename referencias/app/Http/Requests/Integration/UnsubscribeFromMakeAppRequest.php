<?php

namespace App\Http\Requests\Integration;

use App\Models\Client;
use App\Http\Requests\APIBaseRequest;


class UnsubscribeFromMakeAppRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'subdomain' => ['required', 'string'],
            'triggerType' => ['required', 'string', 'in:newLead,newSale,newTask,statusChange'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                //
            }
        });
    }

}
