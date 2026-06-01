<?php

namespace App\Http\Requests\Integration;

use App\Models\Client;
use App\Http\Requests\APIBaseRequest;


class SubscribeToMakeAppRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'url' => ['required', 'url'],
            'subdomain' => ['required', 'string'],
            'triggerType' => ['required', 'string', 'in:newLead,newSale,newTask,statusChange'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
            }
        });
    }

}
