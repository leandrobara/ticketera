<?php

namespace App\Http\Requests\Views;

use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;


class ListLeadIdsByPhoneRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'phone' => ['required', 'string'],
        ];
    }


    public function getPhone()
    {
        $val = parent::validated();
        return $val['phone'];
    }

}
