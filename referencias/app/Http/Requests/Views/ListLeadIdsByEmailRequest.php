<?php

namespace App\Http\Requests\Views;

use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;


class ListLeadIdsByEmailRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'email' => ['required', 'email'],
        ];
    }


    public function getEmail()
    {
        $val = parent::validated();
        return $val['email'];
    }

}
