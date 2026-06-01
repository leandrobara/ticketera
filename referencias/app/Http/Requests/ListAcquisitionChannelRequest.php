<?php

namespace App\Http\Requests;

class ListAcquisitionChannelRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'page' => ['sometimes', 'int'],
            'limit' => ['sometimes', 'int']
        ];
    }
}
